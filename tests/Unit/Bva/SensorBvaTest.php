<?php

namespace Tests\Unit\Bva;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SensorBvaTest extends TestCase
{
    /**
     * Simulasi logika alert sensor dari PeternakanController.php:469-471
     *
     * $suhuWarning   = $data['suhu'] > 30;
     * $humidWarning  = $data['kelembapan'] > 80;
     * $amoniaWarning = $data['amonia'] > 20;
     *
     * Operator: > (batas terbuka). Nilai == ambang TIDAK memicu alert.
     */
    public static function sensorAlertProvider(): array
    {
        return [
            'SNS-01 temp 30.0 tepat di ambang (tidak alert)' => [30.0, 'suhu', false],
            'SNS-02 temp 30.1 di atas ambang (alert)'        => [30.1, 'suhu', true],
            'SNS-03 humid 80.0 tepat di ambang (tidak alert)' => [80.0, 'kelembapan', false],
            'SNS-04 humid 80.1 di atas ambang (alert)'        => [80.1, 'kelembapan', true],
            'SNS-05 amonia 20.0 tepat di ambang (tidak alert)' => [20.0, 'amonia', false],
            'SNS-06 amonia 20.1 di atas ambang (alert)'        => [20.1, 'amonia', true],
        ];
    }

    #[DataProvider('sensorAlertProvider')]
    public function test_sensor_alert_threshold(float $value, string $sensor, bool $expectedAlert)
    {
        $thresholds = [
            'suhu'       => 30.0,
            'kelembapan' => 80.0,
            'amonia'     => 20.0,
        ];

        $actualAlert = $value > $thresholds[$sensor];

        $this->assertSame(
            $expectedAlert,
            $actualAlert,
            "Sensor {$sensor} nilai {$value}: expected alert=" . ($expectedAlert ? 'true' : 'false')
            . " tetapi mendapat " . ($actualAlert ? 'true' : 'false')
            . " (threshold {$thresholds[$sensor]})"
        );
    }
}
