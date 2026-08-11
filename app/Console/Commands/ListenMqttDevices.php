<?php

namespace App\Console\Commands;

use App\Models\IotConnectionConfig;
use App\Services\Iot\MqttSubscriptionService;
use Illuminate\Console\Command;

class ListenMqttDevices extends Command
{
    protected $signature = 'iot:mqtt-listen {connectionId? : ID konfigurasi koneksi MQTT} {--once : Berhenti setelah satu pesan diterima} {--timeout=0 : Batas waktu listener dalam detik, 0 tanpa batas}';

    protected $description = 'Subscribe MQTT broker dan simpan payload sensor ke tabel IoT.';

    public function handle(MqttSubscriptionService $mqtt): int
    {
        $connection = $this->resolveConnection();
        if (! $connection) {
            return self::FAILURE;
        }

        try {
            $broker = $mqtt->brokerSummary($connection);
            $topics = $mqtt->topicsForConnection($connection);

            $this->info("Menghubungkan ke {$broker['scheme']}://{$broker['host']}:{$broker['port']}");
            $this->line('Topic: '.implode(', ', $topics));

            $stats = $mqtt->listen($connection, [
                'once' => (bool) $this->option('once'),
                'timeout' => (int) $this->option('timeout'),
                'client_suffix' => $this->clientSuffix(),
            ]);

            $suppressed = (int) ($stats['suppressed'] ?? 0);
            $this->info("Listener selesai. Pesan: {$stats['messages']}, tersimpan: {$stats['inserted']}, ditahan: {$suppressed}, skip: {$stats['skipped']}.");

            if ($stats['last_topic']) {
                $this->line("Terakhir: {$stats['last_topic']} -> ".($stats['last_device'] ?? 'device tidak dikenali'));
            }

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }

    private function resolveConnection(): ?IotConnectionConfig
    {
        $query = IotConnectionConfig::with('protocol')
            ->withCount(['devices' => function ($query) {
                $query->where(function ($deviceQuery) {
                    $deviceQuery->whereNull('status')
                        ->orWhere('status', '<>', 'maintenance');
                });
            }])
            ->whereNotNull('mqttBrokerUrl')
            ->where('mqttBrokerUrl', '<>', '');

        if ($id = $this->argument('connectionId')) {
            $connection = (clone $query)->where('id', $id)->first();
            if (! $connection) {
                $this->error('Konfigurasi MQTT tidak ditemukan.');
            }

            return $connection;
        }

        $connections = $query->get();
        $connectionsWithDevices = $connections->filter(fn (IotConnectionConfig $connection) => (int) ($connection->devices_count ?? 0) > 0)->values();

        if ($connections->isEmpty()) {
            $this->error('Belum ada konfigurasi MQTT aktif.');
            return null;
        }

        if ($connectionsWithDevices->count() === 1) {
            return $connectionsWithDevices->first();
        }

        if ($connections->count() > 1) {
            $this->warn('Ada lebih dari satu koneksi MQTT yang perlu listener. Jalankan satu listener per koneksi:');
            foreach ($connections as $connection) {
                $label = $connection->protocol->protocolName ?? 'MQTT';
                $deviceCount = (int) ($connection->devices_count ?? 0);
                $this->line("php artisan iot:mqtt-listen {$connection->id}  # {$label} {$connection->mqttBrokerUrl} ({$deviceCount} device)");
            }

            return null;
        }

        return $connections->first();
    }

    private function clientSuffix(): string
    {
        $host = preg_replace('/[^A-Za-z0-9_-]/', '', gethostname() ?: 'host');

        return 'listener-'.$host.'-'.getmypid();
    }
}
