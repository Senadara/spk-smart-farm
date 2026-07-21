<?php

namespace Tests\Unit\TDD;

use App\Services\Fuzzy\FuzzySensorCardMapper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class FuzzySensorCardMapperTest extends TestCase
{
    private FuzzySensorCardMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new FuzzySensorCardMapper();
    }

    public function toIndicators_memetakan_status_danger_ke_warna_merah(): void
    {
        $cards = [
            ['label' => 'Amonia', 'value' => 25.0, 'unit' => 'ppm', 'status' => 'danger', 'percent' => 80, 'set' => 'tinggi'],
        ];

        $expected = [
            ['label' => 'Amonia', 'value' => '25 ppm', 'color' => 'red', 'detail' => 'tinggi', 'score' => 80],
        ];

        $this->assertSame($expected, $this->mapper->toIndicators($cards));
    }

    public function toIndicators_memetakan_warning_ke_amber_dan_normal_ke_emerald(): void
    {
        $cards = [
            ['label' => 'Suhu', 'value' => 28.5, 'unit' => '°C', 'status' => 'warning', 'percent' => 55, 'set' => 'hangat'],
            ['label' => 'HDP',  'value' => 85.0, 'unit' => '%',  'status' => 'normal',  'percent' => 85, 'set' => 'tinggi'],
        ];

        $expected = [
            ['label' => 'Suhu', 'value' => '28.5 °C', 'color' => 'amber',   'detail' => 'hangat', 'score' => 55],
            ['label' => 'HDP',  'value' => '85 %',    'color' => 'emerald', 'detail' => 'tinggi', 'score' => 85],
        ];

        $this->assertSame($expected, $this->mapper->toIndicators($cards));
    }

    public function toIndicators_memakai_nilai_default_saat_field_hilang(): void
    {
        $cards = [[]]; 

        $expected = [
            ['label' => '-', 'value' => '0', 'color' => 'emerald', 'detail' => '-', 'score' => 0],
        ];

        $this->assertSame($expected, $this->mapper->toIndicators($cards));
    }

    public function toSpider_membangun_label_dan_nilai_persen(): void
    {
        $cards = [
            ['label' => 'Suhu',   'percent' => 60],
            ['label' => 'Amonia', 'percent' => 80],
        ];

        $expected = [
            'labels' => ['Suhu', 'Amonia'],
            'values' => [60, 80],
        ];

        $this->assertSame($expected, $this->mapper->toSpider($cards));
    }

    public function toSpider_default_saat_field_hilang(): void
    {
        $cards = [
            ['label' => 'X'],  
            ['percent' => 40],  
        ];

        $expected = [
            'labels' => ['X', '-'],
            'values' => [0, 40],
        ];

        $this->assertSame($expected, $this->mapper->toSpider($cards));
    }

    public function fromResult_mengembalikan_kartu_kosong_saat_input_kosong(): void
    {
        $expected = [
            'lingkungan'    => [],
            'kesehatan'     => [],
            'produktivitas' => [],
        ];

        $this->assertSame($expected, $this->mapper->fromResult([]));
        $this->assertSame($expected, $this->mapper->fromResult(['inputs' => []]));
    }

    public function dominantSet_mengembalikan_key_dengan_derajat_tertinggi(): void
    {
        $method = new ReflectionMethod(FuzzySensorCardMapper::class, 'dominantSet');
        $method->setAccessible(true);

        $this->assertSame('tinggi', $method->invoke($this->mapper, ['rendah' => 0.2, 'tinggi' => 0.8]));
        $this->assertNull($method->invoke($this->mapper, []));
        $this->assertNull($method->invoke($this->mapper, ['a' => 0.0, 'b' => 0.0]));
    }

    public function valueWithUnit_memformat_angka_dan_satuan(): void
    {
        $method = new ReflectionMethod(FuzzySensorCardMapper::class, 'valueWithUnit');
        $method->setAccessible(true);

        $this->assertSame('85 %',     $method->invoke($this->mapper, 85.0,   '%'));
        $this->assertSame('28.5 °C',  $method->invoke($this->mapper, 28.5,   '°C'));
        $this->assertSame('0',        $method->invoke($this->mapper, 0.0,    ''));
        $this->assertSame('12.34 kg', $method->invoke($this->mapper, 12.344, 'kg'));
    }
}
