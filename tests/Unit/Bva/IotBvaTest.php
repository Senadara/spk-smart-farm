<?php

namespace Tests\Unit\Bva;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;

/**
 * BVA untuk validasi field IoT.
 * Menguji rule validasi langsung (tanpa HTTP request) untuk menghindari CSRF.
 *
 * Rule dari IotController.php:
 * - mqttPort:       integer|min:1|max:65535
 * - mqttKeepAlive:  integer|min:5|max:65535
 * - mqttQos:        integer|min:0|max:2
 * - pollingInterval: integer|min:10|max:86400
 */
class IotBvaTest extends TestCase
{
    #[DataProvider('mqttPortProvider')]
    public function test_mqtt_port_validation(int $port, bool $expectedValid, string $label)
    {
        $validator = Validator::make(['mqttPort' => $port], [
            'mqttPort' => 'integer|min:1|max:65535',
        ]);

        $this->assertSame($expectedValid, $validator->passes(),
            "mqttPort={$port}: expected valid=" . ($expectedValid ? 'true' : 'false') . " — {$label}"
        );
    }

    public static function mqttPortProvider(): array
    {
        return [
            'IOT-08 mqttPort=0  (min-1) invalid'   => [0, false,   '0 < 1 → reject'],
            'IOT-09 mqttPort=1  (min on) valid'    => [1, true,    '1 = batas bawah'],
            'IOT-10 mqttPort=2  (min+1) valid'     => [2, true,    '2 > 1 valid'],
            'IOT-11 mqttPort=32768 (nominal) valid' => [32768, true, 'nilai tengah'],
            'IOT-12 mqttPort=65534 (max-1) valid'  => [65534, true, '65534 < 65535'],
            'IOT-13 mqttPort=65535 (max on) valid' => [65535, true, '65535 = batas atas'],
            'IOT-14 mqttPort=65536 (max+1) invalid'=> [65536, false,  '65536 > 65535 → reject'],
        ];
    }

    #[DataProvider('mqttKeepAliveProvider')]
    public function test_mqtt_keep_alive_validation(int $value, bool $expectedValid, string $label)
    {
        $validator = Validator::make(['mqttKeepAlive' => $value], [
            'mqttKeepAlive' => 'integer|min:5|max:65535',
        ]);
        $this->assertSame($expectedValid, $validator->passes(),
            "mqttKeepAlive={$value}: expected valid=" . ($expectedValid ? 'true' : 'false') . " — {$label}"
        );
    }

    public static function mqttKeepAliveProvider(): array
    {
        return [
            'IOT-20 keepAlive=4     (min-1) invalid'   => [4, false,    '4 < 5 → reject'],
            'IOT-21 keepAlive=5     (min on) valid'    => [5, true,     '5 = batas bawah'],
            'IOT-22 keepAlive=6     (min+1) valid'     => [6, true,     '6 > 5 valid'],
            'IOT-23 keepAlive=32768 (nominal) valid'  => [32768, true,  'nilai tengah'],
            'IOT-24 keepAlive=65534 (max-1) valid'    => [65534, true,  '65534 < 65535'],
            'IOT-25 keepAlive=65535 (max on) valid'   => [65535, true,  '65535 = batas atas'],
            'IOT-26 keepAlive=65536 (max+1) invalid'  => [65536, false, '65536 > 65535 → reject'],
        ];
    }

    #[DataProvider('mqttQosProvider')]
    public function test_mqtt_qos_validation(int $value, bool $expectedValid, string $label)
    {
        $validator = Validator::make(['mqttQos' => $value], [
            'mqttQos' => 'integer|min:0|max:2',
        ]);
        $this->assertSame($expectedValid, $validator->passes(),
            "mqttQos={$value}: expected valid=" . ($expectedValid ? 'true' : 'false') . " — {$label}"
        );
    }

    public static function mqttQosProvider(): array
    {
        return [
            'IOT-15 qos=-1 (min-1) invalid' => [-1, false, '-1 < 0 → reject'],
            'IOT-16 qos=0  (min on) valid'  => [0, true,   '0 = batas bawah'],
            'IOT-17 qos=1  (min+1) valid'   => [1, true,   '1 di antara 0-2'],
            'IOT-18 qos=2  (max on) valid'  => [2, true,   '2 = batas atas'],
            'IOT-19 qos=3  (max+1) invalid' => [3, false,  '3 > 2 → reject'],
        ];
    }

    #[DataProvider('pollingIntervalProvider')]
    public function test_polling_interval_validation(int $value, bool $expectedValid, string $label)
    {
        $validator = Validator::make(['pollingInterval' => $value], [
            'pollingInterval' => 'integer|min:10|max:86400',
        ]);
        $this->assertSame($expectedValid, $validator->passes(),
            "pollingInterval={$value}: expected valid=" . ($expectedValid ? 'true' : 'false') . " — {$label}"
        );
    }

    public static function pollingIntervalProvider(): array
    {
        return [
            'IOT-01 polling=9     (min-1) invalid'   => [9, false,    '9 < 10 → reject'],
            'IOT-02 polling=10    (min on) valid'    => [10, true,    '10 = batas bawah'],
            'IOT-03 polling=11    (min+1) valid'     => [11, true,    '11 > 10 valid'],
            'IOT-04 polling=43200 (nominal) valid'  => [43200, true,  'nilai tengah'],
            'IOT-05 polling=86399 (max-1) valid'    => [86399, true,  '86399 < 86400'],
            'IOT-06 polling=86400 (max on) valid'   => [86400, true,  '86400 = batas atas'],
            'IOT-07 polling=86401 (max+1) invalid'  => [86401, false, '86401 > 86400 → reject'],
        ];
    }
}
