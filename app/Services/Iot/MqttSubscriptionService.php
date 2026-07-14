<?php

namespace App\Services\Iot;

use App\Models\IotConnectionConfig;
use App\Models\IotDevice;
use App\Models\IotDeviceLog;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttSubscriptionService
{
    public function __construct(private readonly IotPayloadIngestor $ingestor)
    {
    }

    public function listen(IotConnectionConfig $connection, array $options = []): array
    {
        $this->ensureClientAvailable();

        $devices = $this->devicesForConnection($connection);
        $topics = $this->topicsForConnection($connection, $devices);

        if ($topics === []) {
            throw new \RuntimeException('Tidak ada topic MQTT aktif. Isi topic koneksi atau topic per device.');
        }

        [$client, $broker] = $this->makeClient($connection, $options['client_suffix'] ?? null);
        $stats = [
            'connected' => false,
            'broker' => $broker,
            'topics' => $topics,
            'messages' => 0,
            'inserted' => 0,
            'skipped' => 0,
            'last_topic' => null,
            'last_device' => null,
        ];

        $settings = $this->connectionSettings($connection, $broker);
        $client->connect($settings, true);
        $stats['connected'] = true;

        $timeout = max(0, (int) ($options['timeout'] ?? 0));
        if ($timeout > 0) {
            $client->registerLoopEventHandler(function (MqttClient $mqtt, float $elapsedTime) use ($timeout): void {
                if ($elapsedTime >= $timeout) {
                    $mqtt->interrupt();
                }
            });
        }

        $once = (bool) ($options['once'] ?? false);
        $qos = $this->qos($connection);

        foreach ($topics as $topic) {
            $client->subscribe($topic, function (string $topic, string $message) use (&$stats, $connection, $devices, $client, $once): void {
                $stats['messages']++;
                $stats['last_topic'] = $topic;

                $device = $this->resolveDevice($connection, $devices, $topic, $message);
                if (! $device) {
                    $stats['skipped']++;
                    return;
                }

                if ($device->status !== 'active') {
                    $stats['skipped']++;
                    $stats['last_device'] = "{$device->deviceCode} ({$device->status})";
                    $this->logInactiveDeviceMessage($device, $topic);

                    if ($once) {
                        $client->interrupt();
                    }

                    return;
                }

                $result = $this->ingestor->ingest($device, $message, "mqtt:{$topic}");
                $stats['inserted'] += (int) $result['inserted'];
                $stats['skipped'] += (int) $result['skipped'];
                $stats['last_device'] = $device->deviceCode;

                if ($once) {
                    $client->interrupt();
                }
            }, $qos);
        }

        $client->loop(true);
        $client->disconnect();

        return $stats;
    }

    public function test(IotConnectionConfig $connection, int $timeout = 5): array
    {
        return $this->listen($connection, [
            'timeout' => max(1, min($timeout, 15)),
            'once' => true,
            'client_suffix' => 'test-'.substr((string) now()->timestamp, -6),
        ]);
    }

    public function topicsForConnection(IotConnectionConfig $connection, $devices = null): array
    {
        $devices ??= $this->devicesForConnection($connection);
        $topics = [];

        foreach ($devices as $device) {
            $deviceTopic = trim((string) ($device->mqttTopic ?? ''));
            if ($deviceTopic !== '') {
                $topics[] = $deviceTopic;
                continue;
            }

            $defaultTopic = trim((string) ($connection->mqttTopic ?? ''));
            if ($defaultTopic !== '' && str_contains($defaultTopic, '{deviceCode}')) {
                $topics[] = str_replace('{deviceCode}', $device->deviceCode, $defaultTopic);
            }
        }

        $defaultTopic = trim((string) ($connection->mqttTopic ?? ''));
        if ($defaultTopic !== '' && ! str_contains($defaultTopic, '{deviceCode}')) {
            $topics[] = $defaultTopic;
        }

        return array_values(array_unique(array_filter($topics)));
    }

    public function brokerSummary(IotConnectionConfig $connection): array
    {
        return $this->parseBroker($connection);
    }

    private function devicesForConnection(IotConnectionConfig $connection)
    {
        return IotDevice::with('parameterMappings.parameter')
            ->where('connectionConfigId', $connection->id)
            ->where('status', 'active')
            ->get();
    }

    private function makeClient(IotConnectionConfig $connection, ?string $suffix = null): array
    {
        $broker = $this->parseBroker($connection);
        $clientId = trim((string) ($connection->mqttClientId ?? ''));

        if ($clientId === '') {
            $clientId = 'smartfarm-laravel-'.substr(str_replace('-', '', (string) $connection->id), 0, 12);
        }

        if ($suffix) {
            $clientId .= '-'.$suffix;
        }

        return [
            new MqttClient($broker['host'], $broker['port'], $clientId, MqttClient::MQTT_3_1_1),
            $broker,
        ];
    }

    private function connectionSettings(IotConnectionConfig $connection, array $broker): ConnectionSettings
    {
        $settings = (new ConnectionSettings())
            ->setConnectTimeout(10)
            ->setSocketTimeout(5)
            ->setKeepAliveInterval(max(5, (int) ($connection->mqttKeepAlive ?? 60)))
            ->setUseTls((bool) $broker['tls']);

        if ($connection->mqttUsername) {
            $settings = $settings->setUsername($connection->mqttUsername);
        }

        if ($connection->mqttPassword) {
            $settings = $settings->setPassword($connection->mqttPassword);
        }

        return $settings;
    }

    private function parseBroker(IotConnectionConfig $connection): array
    {
        $raw = trim((string) ($connection->mqttBrokerUrl ?? ''));
        if ($raw === '') {
            throw new \InvalidArgumentException('MQTT Broker URL wajib diisi.');
        }

        $url = preg_match('#^[a-z][a-z0-9+.-]*://#i', $raw) ? $raw : 'mqtt://'.$raw;
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            throw new \InvalidArgumentException('Format MQTT Broker URL tidak valid.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'mqtt'));
        if (in_array($scheme, ['ws', 'wss'], true) || ! empty($parts['path'])) {
            throw new \InvalidArgumentException('Endpoint WebSocket MQTT belum didukung listener PHP. Gunakan host MQTT TCP/TLS port 1883 atau 8883.');
        }

        $port = (int) ($connection->mqttPort ?: ($parts['port'] ?? 0));
        $tls = (bool) ($connection->mqttUseTls ?? false);

        if ($scheme === 'mqtts' || $scheme === 'ssl' || $scheme === 'tls') {
            $tls = true;
        }

        if ($port <= 0) {
            $port = $tls ? 8883 : 1883;
        }

        if ($port === 8883) {
            $tls = true;
        }

        return [
            'host' => $parts['host'],
            'port' => $port,
            'tls' => $tls,
            'scheme' => $tls ? 'mqtts' : 'mqtt',
        ];
    }

    private function resolveDevice(IotConnectionConfig $connection, $devices, string $topic, string $message): ?IotDevice
    {
        $payloadDeviceCode = $this->ingestor->deviceCodeFromPayload($message);
        if ($payloadDeviceCode) {
            $match = $devices->firstWhere('deviceCode', $payloadDeviceCode);
            if ($match) {
                return $match;
            }

            $knownDevice = IotDevice::with('parameterMappings.parameter')
                ->where('connectionConfigId', $connection->id)
                ->where('deviceCode', $payloadDeviceCode)
                ->first();

            if ($knownDevice) {
                return $knownDevice;
            }
        }

        foreach ($devices as $device) {
            $deviceTopic = trim((string) ($device->mqttTopic ?? ''));
            if ($deviceTopic !== '' && $this->topicMatches($deviceTopic, $topic)) {
                return $device;
            }
        }

        $defaultTopic = trim((string) ($connection->mqttTopic ?? ''));
        if ($devices->count() === 1 && ($defaultTopic === '' || $this->topicMatches($defaultTopic, $topic))) {
            return $devices->first();
        }

        return null;
    }

    private function logInactiveDeviceMessage(IotDevice $device, string $topic): void
    {
        try {
            IotDeviceLog::create([
                'deviceId' => $device->id,
                'logType' => 'WARNING',
                'message' => "[mqtt:{$topic}] Payload diterima, tetapi device {$device->deviceCode} berstatus {$device->status}. Aktifkan device agar data masuk monitoring.",
            ]);
        } catch (\Throwable) {
            // Logging should not break the listener loop.
        }
    }

    private function topicMatches(string $filter, string $topic): bool
    {
        if ($filter === $topic) {
            return true;
        }

        $pattern = preg_quote($filter, '#');
        $pattern = str_replace(['\+', '\#'], ['[^/]+', '.*'], $pattern);

        return (bool) preg_match('#^'.$pattern.'$#', $topic);
    }

    private function qos(IotConnectionConfig $connection): int
    {
        return min(2, max(0, (int) ($connection->mqttQos ?? 0)));
    }

    private function ensureClientAvailable(): void
    {
        if (! class_exists(MqttClient::class)) {
            throw new \RuntimeException('Dependency MQTT belum tersedia. Jalankan: composer require php-mqtt/client');
        }
    }
}
