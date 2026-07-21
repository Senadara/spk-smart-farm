<?php

namespace Tests\Unit\Bva;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use App\Models\MasterSupplier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BVA untuk rating (decimal 3,1) dan jarak_km (unsignedInteger).
 *
 * TEMUAN T-01: Controller tidak memvalidasi min untuk kedua field.
 * - rating decimal(3,1) TIDAK unsigned — nilai negatif TERSIMPAN.
 * - jarak_km unsignedInteger — MySQL tolak nilai negatif (QueryException).
 */
class SupplierRatingBvaTest extends TestCase
{
    use DatabaseTransactions;

    #[DataProvider('ratingProvider')]
    public function test_rating_bva(float $nilai, bool $tersimpan, string $label)
    {
        try {
            MasterSupplier::create([
                'nama'    => 'BVA Supplier',
                'rating'  => $nilai,
                'alamat'  => 'Test',
                'kontak'  => '08123456789',
            ]);
            $baru = MasterSupplier::where('nama', 'BVA Supplier')->first();

            if ($tersimpan) {
                $this->assertNotNull($baru, "{$label}: harus tersimpan");
                $this->assertEquals($nilai, (float) $baru->rating, "{$label}: nilai harus sesuai");
            } else {
                $this->fail("{$label}: DUGGAAN gagal — seharusnya tidak tersimpan tetapi tidak ada exception. Nilai {$nilai} tersimpan dengan rating=" . ($baru?->rating ?? 'null'));
            }
        } catch (\Illuminate\Database\QueryException $e) {
            if (!$tersimpan) {
                $this->assertTrue(true, "{$label}: MySQL tolak nilai {$nilai} (expected)");
            } else {
                throw $e;
            }
        }
    }

    public static function ratingProvider(): array
    {
        // decimal(3,1): 3 digit total, 1 desimal → -99.9 s.d. 99.9
        return [
            'SUP-01 rating=-0.1 TERSIMPAN (decimal signed)' => [-0.1, true,  'TEMUAN: rating negatif TERSIMPAN karena kolom signed'],
            'SUP-02 rating=0.0 min on-point'                => [0.0,  true,  'rating 0.0 valid'],
            'SUP-03 rating=9.9 max kolom'                  => [9.9,  true,  'rating 9.9 batas maks kolom'],
            'SUP-04 rating=99.9 maks decimal(3,1)'              => [99.9, true,  'rating 99.9 batas maks kolom'],
            'SUP-04b rating=100.0 OVERFLOW decimal(3,1)'          => [100.0, false, 'rating 100.0 overflow decimal(3,1)'],
        ];
    }

    #[DataProvider('jarakKmProvider')]
    public function test_jarak_km_bva(int $nilai, bool $tersimpan, string $label)
    {
        try {
            MasterSupplier::create([
                'nama'     => 'BVA Jarak',
                'jarak_km' => $nilai,
                'alamat'   => 'Test',
                'kontak'   => '08123456789',
            ]);

            if ($tersimpan) {
                $this->assertDatabaseHas('master_suppliers', [
                    'nama' => 'BVA Jarak', 'jarak_km' => $nilai,
                ]);
            } else {
                $this->fail("{$label}: DUGGAAN — jarak_km={$nilai} seharusnya ditolak MySQL tapi tersimpan");
            }
        } catch (\Illuminate\Database\QueryException $e) {
            if (!$tersimpan) {
                $this->assertTrue(true, "{$label}: MySQL tolak nilai {$nilai} (expected)");
            } else {
                throw $e;
            }
        }
    }

    public static function jarakKmProvider(): array
    {
        return [
            'SUP-08 jarak_km=-1 MYSQL ERROR (unsigned)'   => [-1, false, 'TEMUAN: jarak_km=-1 → MySQL QueryException, BUKAN 422 karena tidak ada validasi Laravel'],
            'SUP-09 jarak_km=0 min on-point'              => [0,  true,  'jarak_km=0 valid'],
            'SUP-10 jarak_km=1 min+1'                     => [1,  true,  'jarak_km=1 valid'],
            'SUP-11 jarak_km=500 nominal'                 => [500, true, 'jarak_km=500 valid'],
            'SUP-12 jarak_km=4294967295 max unsignedInteger' => [4294967295, true, 'jarak_km maks unsignedInteger'],
        ];
    }
}
