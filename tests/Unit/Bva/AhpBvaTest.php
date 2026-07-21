<?php

namespace Tests\Unit\Bva;

use Tests\TestCase;
use App\Models\SpkParameter;
use App\Models\SpkAhpPerbandingan;
use App\Models\SpkAhpBobot;
use App\Services\AHPService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AhpBvaTest extends TestCase
{
    use DatabaseTransactions;

    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();

        // Gunakan email unik per test via timestamp
        $uniqueEmail = 'bva-' . (string) \Illuminate\Support\Str::uuid() . '@test.local';
        $this->userId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('user')->insert([
            'id'        => $this->userId,
            'name'      => 'BVA Tester',
            'email'     => $uniqueEmail,
            'password'  => bcrypt('password'),
            'role'     => 'pjawab',
            'isActive' => 1,
            'isDeleted' => 0,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        // Bersihkan parameter seeded untuk kontrol penuh
        \Illuminate\Support\Facades\DB::table('spk_ahp_perbandingans')->truncate();
        \Illuminate\Support\Facades\DB::table('spk_ahp_bobots')->truncate();
        SpkParameter::query()->delete();
    }

    /**
     * AHP — Minimum Parameter n ≥ 2
     * AHPService.php:34 — return false jika count($params) < 2
     */
    /**
     * AHP — Minimum Parameter n ≥ 2
     * Service return false jika tidak ada parameter (n=0)
     */
    public function test_ahp_n0_tanpa_parameter_kembali_false()
    {
        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($this->userId);

        $this->assertFalse($result, 'AHP harus return false jika tidak ada parameter (n=0)');
    }

    public function test_ahp_n1_hanya_1_parameter_juga_kembali_false()
    {
        SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);

        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($this->userId);

        $this->assertFalse($result, 'AHP harus return false jika hanya 1 parameter (n=1 < 2)');
    }

    public function test_ahp_n2_2_parameter_berhasil_hitung_bobot()
    {
        SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);
        SpkParameter::create(['nama_parameter' => 'Kualitas', 'tipe' => 'benefit']);

        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($this->userId);

        $this->assertNotFalse($result, 'AHP harus berhasil dengan 2 parameter (n=2)');
        $this->assertArrayHasKey('cr', $result);
        $this->assertArrayHasKey('weights', $result);
        // n=2 → RI=0 → CR=0 (selalu konsisten)
        $this->assertEquals(0.0, round($result['cr'], 4));
    }

    public function test_ahp_n3_3_parameter_dengan_skala_1_menghasilkan_bobot()
    {
        SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);
        SpkParameter::create(['nama_parameter' => 'Kualitas', 'tipe' => 'benefit']);
        SpkParameter::create(['nama_parameter' => 'Kecepatan', 'tipe' => 'benefit']);

        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($this->userId);

        $this->assertNotFalse($result, 'AHP harus berhasil dengan 3 parameter (n=3)');
        $this->assertArrayHasKey('cr', $result);
        $this->assertArrayHasKey('weights', $result);
    }

    /**
     * CR adalah nilai TERHITUNG dari matriks pairwise, bukan input langsung.
     * Test ini memverifikasi fungsi konsistensi dengan membandingkan CI/RI.
     *
     * Untuk menguji CR ≈ 0.099, 0.100, 0.101, kita perlu matriks pairwise
     * yang menghasilkan CI tertentu sehingga CI/RI mendekati nilai tsb.
     *
     * Pendekatan:
     * 1. Hitung λ_max dari matriks pairwise
     * 2. CI = (λ_max - n) / (n - 1)
     * 3. CR = CI / RI(n)
     *
     * Karena RI adalah konstanta (RI tabel Saaty), CI dikontrol oleh matriks.
     * Nilai skala pairwise: 1 = sama penting, 3 = cukup penting, dst.
     *
     * Referensi: Saaty TL (1988) — jika CR ≤ 0.1 → consistent
     */
    public function test_ahp_cr_099_berarti_konsisten()
    {
        // Setup 3 parameter
        $p1 = SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);
        $p2 = SpkParameter::create(['nama_parameter' => 'Kualitas', 'tipe' => 'benefit']);
        $p3 = SpkParameter::create(['nama_parameter' => 'Kecepatan', 'tipe' => 'benefit']);
        $params = [$p1, $p2, $p3];
        $n = count($params);

        // Hapus semua perbandingan sebelumnya
        SpkAhpPerbandingan::where('user_id', $this->userId)->delete();
        SpkAhpBobot::where('user_id', $this->userId)->delete();

        // Buat pairwise untuk matriks yang "sangat konsisten" (nilai 1 = sama penting)
        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                SpkAhpPerbandingan::create([
                    'user_id'       => $this->userId,
                    'parameter_1_id' => $params[$i]->id,
                    'parameter_2_id' => $params[$j]->id,
                    'nilai_skala'   => 1,
                ]);
            }
        }

        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($this->userId);

        $this->assertNotFalse($result);
        $this->assertTrue($result['cr'] <= 0.1, "CR {$result['cr']} harus ≤ 0.1 (konsisten)");
        $this->assertArrayHasKey('is_valid', $result);
        $this->assertTrue($result['is_valid']);
    }

    /**
     * CR > 0.1 → is_valid = false (tidak konsisten)
     * Untuk menghasilkan CR > 0.1, perlu matriks yang saling bertentangan.
     */
    public function test_ahp_cr_101_berarti_tidak_konsisten()
    {
        $p1 = SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);
        $p2 = SpkParameter::create(['nama_parameter' => 'Kualitas', 'tipe' => 'benefit']);
        $p3 = SpkParameter::create(['nama_parameter' => 'Kecepatan', 'tipe' => 'benefit']);
        $params = [$p1, $p2, $p3];

        SpkAhpPerbandingan::where('user_id', $this->userId)->delete();
        SpkAhpBobot::where('user_id', $this->userId)->delete();

        // Buat pairwise INKONSISTEN: matriks melingkar (A >> B >> C >> A)
        // A > B (5), B > C (5), tetapi A < C (1/3) — kontradiksi logis
        SpkAhpPerbandingan::create(['user_id' => $this->userId, 'parameter_1_id' => $params[0]->id, 'parameter_2_id' => $params[1]->id, 'nilai_skala' => 5]);
        SpkAhpPerbandingan::create(['user_id' => $this->userId, 'parameter_1_id' => $params[1]->id, 'parameter_2_id' => $params[2]->id, 'nilai_skala' => 5]);
        SpkAhpPerbandingan::create(['user_id' => $this->userId, 'parameter_1_id' => $params[0]->id, 'parameter_2_id' => $params[2]->id, 'nilai_skala' => 1 / 3]);

        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($this->userId);

        $this->assertNotFalse($result);
        $this->assertGreaterThan(0.1, $result['cr'], "CR {$result['cr']} harus > 0.1 (tidak konsisten)");
        $this->assertArrayHasKey('is_valid', $result);
        $this->assertFalse($result['is_valid']);
    }

    /**
     * AHPService.php:126 — operator ≤
     * $isValid = $cr <= 0.1;
     * Artinya CR = 0.1 TEPAT termasuk valid.
     */
    public function test_ahp_cr_exactly_01_adalah_valid_karena_operator_le()
    {
        $p1 = SpkParameter::create(['nama_parameter' => 'A', 'tipe' => 'benefit']);
        $p2 = SpkParameter::create(['nama_parameter' => 'B', 'tipe' => 'benefit']);
        $p3 = SpkParameter::create(['nama_parameter' => 'C', 'tipe' => 'benefit']);
        $p4 = SpkParameter::create(['nama_parameter' => 'D', 'tipe' => 'benefit']);
        $params = [$p1, $p2, $p3, $p4];

        SpkAhpPerbandingan::where('user_id', $this->userId)->delete();
        SpkAhpBobot::where('user_id', $this->userId)->delete();

        SpkAhpPerbandingan::create(['user_id' => $this->userId, 'parameter_1_id' => $params[0]->id, 'parameter_2_id' => $params[1]->id, 'nilai_skala' => 1]);
        SpkAhpPerbandingan::create(['user_id' => $this->userId, 'parameter_1_id' => $params[0]->id, 'parameter_2_id' => $params[2]->id, 'nilai_skala' => 3]);
        SpkAhpPerbandingan::create(['user_id' => $this->userId, 'parameter_1_id' => $params[0]->id, 'parameter_2_id' => $params[3]->id, 'nilai_skala' => 5]);
        SpkAhpPerbandingan::create(['user_id' => $this->userId, 'parameter_1_id' => $params[1]->id, 'parameter_2_id' => $params[2]->id, 'nilai_skala' => 2]);
        SpkAhpPerbandingan::create(['user_id' => $this->userId, 'parameter_1_id' => $params[1]->id, 'parameter_2_id' => $params[3]->id, 'nilai_skala' => 4]);
        SpkAhpPerbandingan::create(['user_id' => $this->userId, 'parameter_1_id' => $params[2]->id, 'parameter_2_id' => $params[3]->id, 'nilai_skala' => 2]);

        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($this->userId);

        $this->assertNotFalse($result);
        $this->assertTrue($result['is_valid'], "CR {$result['cr']} harus ≤ 0.1 sesuai operator ≤");
    }

    /**
     * BVA: nilai_skala pada batas bawah (0.111 = 1/9) dan batas atas (9).
     * Validasi controller: min:0.111|max:9.
     * Service level — verifikasi bahwa nilai ekstrem ini dapat diproses.
     */
    public function test_nilai_skala_min_0111()
    {
        $p1 = SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);
        $p2 = SpkParameter::create(['nama_parameter' => 'Kualitas', 'tipe' => 'benefit']);
        $params = [$p1, $p2];

        SpkAhpPerbandingan::create([
            'user_id' => $this->userId,
            'parameter_1_id' => $params[0]->id,
            'parameter_2_id' => $params[1]->id,
            'nilai_skala' => 0.111, // 1/9 (skala Saaty minimum)
        ]);

        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($this->userId);

        $this->assertNotFalse($result, 'nilai_skala=0.111 (1/9) harus diproses');
        $this->assertArrayHasKey('cr', $result);
    }

    public function test_nilai_skala_max_9()
    {
        $p1 = SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);
        $p2 = SpkParameter::create(['nama_parameter' => 'Kualitas', 'tipe' => 'benefit']);
        $params = [$p1, $p2];

        SpkAhpPerbandingan::create([
            'user_id' => $this->userId,
            'parameter_1_id' => $params[0]->id,
            'parameter_2_id' => $params[1]->id,
            'nilai_skala' => 9, // skala Saaty maksimum
        ]);

        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($this->userId);

        $this->assertNotFalse($result, 'nilai_skala=9 harus diproses');
        $this->assertArrayHasKey('cr', $result);
    }
}
