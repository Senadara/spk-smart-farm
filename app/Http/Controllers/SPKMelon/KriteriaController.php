<?php

namespace App\Http\Controllers\SPKMelon;

use App\Http\Controllers\Controller;
use App\Http\Requests\SPKMelon\StoreKriteriaRequest;
use App\Http\Requests\SPKMelon\UpdateKriteriaRequest;
use App\Models\SPKMelon\SpkMelonKriteria;
use App\Services\SPKMelon\KriteriaService;
use App\Services\SPKMelon\FuzzyAhpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KriteriaController extends Controller
{
    public function __construct(protected KriteriaService $service)
    {
    }

    /**
     * Halaman daftar kriteria SPK dengan filter kategori.
     * Route: GET /spk-melon/kriteria
     */
    public function index(Request $request): View
    {
        $filterKategori = $request->query('kategori');

        // Validasi nilai filter untuk keamanan
        if ($filterKategori && !in_array($filterKategori, ['produktivitas', 'kualitas', 'lingkungan'])) {
            $filterKategori = null;
        }

        $query = SpkMelonKriteria::orderBy('kode', 'asc');

        if ($filterKategori) {
            $query->byKategori($filterKategori);
        }

        $daftarKriteria = $query->get();

        // Hitung total kriteria per tipe evaluasi untuk info card status range
        $totalProduktivitas = SpkMelonKriteria::where(function ($q) {
            $q->where('kategori', 'produktivitas')
              ->orWhere('kategori', 'lingkungan');
        })->count();

        $totalKualitas = SpkMelonKriteria::where(function ($q) {
            $q->where('kategori', 'kualitas')
              ->orWhere('kategori', 'lingkungan');
        })->count();

        return view('spk-melon.kriteria.index', [
            'daftarKriteria'      => $daftarKriteria,
            'filterKategori'      => $filterKategori,
            'nextKode'            => $this->service->generateNextKode(),
            'totalProduktivitas'  => $totalProduktivitas,
            'totalKualitas'       => $totalKualitas,
            'minKriteria'         => FuzzyAhpService::MIN_KRITERIA,
            'maxKriteria'         => FuzzyAhpService::MAX_KRITERIA,
        ]);
    }

    /**
     * Simpan kriteria baru (dari modal form tambah).
     * Route: POST /spk-melon/kriteria
     */
    public function store(StoreKriteriaRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $kategoriBaru = $validated['kategori'];

        // Validasi range: cek apakah penambahan ini akan menyebabkan
        // total kriteria untuk tipe evaluasi mana pun melebihi MAX_KRITERIA
        $max = FuzzyAhpService::MAX_KRITERIA;

        $cekProduktivitas = ($kategoriBaru === 'produktivitas' || $kategoriBaru === 'lingkungan');
        $cekKualitas      = ($kategoriBaru === 'kualitas'      || $kategoriBaru === 'lingkungan');

        if ($cekProduktivitas) {
            $totalProduktivitas = SpkMelonKriteria::where(function ($q) {
                $q->where('kategori', 'produktivitas')
                  ->orWhere('kategori', 'lingkungan');
            })->count();

            if (($totalProduktivitas + 1) > $max) {
                return redirect()
                    ->route('spk-melon.kriteria.index')
                    ->with('error', "Tidak dapat menambah kriteria: total kriteria untuk evaluasi Produktivitas akan melebihi batas maksimum {$max} (saat ini {$totalProduktivitas}, akan menjadi " . ($totalProduktivitas + 1) . "). Hapus kriteria existing terlebih dahulu sebelum menambah.");
            }
        }

        if ($cekKualitas) {
            $totalKualitas = SpkMelonKriteria::where(function ($q) {
                $q->where('kategori', 'kualitas')
                  ->orWhere('kategori', 'lingkungan');
            })->count();

            if (($totalKualitas + 1) > $max) {
                return redirect()
                    ->route('spk-melon.kriteria.index')
                    ->with('error', "Tidak dapat menambah kriteria: total kriteria untuk evaluasi Kualitas akan melebihi batas maksimum {$max} (saat ini {$totalKualitas}, akan menjadi " . ($totalKualitas + 1) . "). Hapus kriteria existing terlebih dahulu sebelum menambah.");
            }
        }

        // Lolos validasi, lanjut create
        $this->service->create($validated);

        return redirect()
            ->route('spk-melon.kriteria.index')
            ->with('success', 'Kriteria berhasil ditambahkan.');
    }

    /**
     * Update kriteria (dari modal form edit).
     * Route: PUT /spk-melon/kriteria/{kriteria}
     */
    public function update(UpdateKriteriaRequest $request, SpkMelonKriteria $kriteria): RedirectResponse
    {
        $this->service->update($kriteria, $request->validated());

        return redirect()
            ->route('spk-melon.kriteria.index')
            ->with('success', 'Kriteria berhasil diperbarui.');
    }

    /**
     * Soft delete kriteria (isDeleted = 1, data tetap tersimpan di database).
     * Route: DELETE /spk-melon/kriteria/{kriteria}
     */
    public function destroy(SpkMelonKriteria $kriteria): RedirectResponse
    {
        $kategoriHapus = $kriteria->kategori;

        // Validasi range: cek apakah penghapusan ini akan menyebabkan
        // total kriteria untuk tipe evaluasi mana pun jatuh di bawah MIN_KRITERIA
        $min = FuzzyAhpService::MIN_KRITERIA;

        $cekProduktivitas = ($kategoriHapus === 'produktivitas' || $kategoriHapus === 'lingkungan');
        $cekKualitas      = ($kategoriHapus === 'kualitas'      || $kategoriHapus === 'lingkungan');

        if ($cekProduktivitas) {
            $totalProduktivitas = SpkMelonKriteria::where(function ($q) {
                $q->where('kategori', 'produktivitas')
                  ->orWhere('kategori', 'lingkungan');
            })->count();

            if (($totalProduktivitas - 1) < $min) {
                return redirect()
                    ->route('spk-melon.kriteria.index')
                    ->with('error', "Tidak dapat menghapus kriteria: total kriteria untuk evaluasi Produktivitas akan jatuh di bawah batas minimum {$min} (saat ini {$totalProduktivitas}, akan menjadi " . ($totalProduktivitas - 1) . "). Tambahkan kriteria pengganti terlebih dahulu sebelum menghapus.");
            }
        }

        if ($cekKualitas) {
            $totalKualitas = SpkMelonKriteria::where(function ($q) {
                $q->where('kategori', 'kualitas')
                  ->orWhere('kategori', 'lingkungan');
            })->count();

            if (($totalKualitas - 1) < $min) {
                return redirect()
                    ->route('spk-melon.kriteria.index')
                    ->with('error', "Tidak dapat menghapus kriteria: total kriteria untuk evaluasi Kualitas akan jatuh di bawah batas minimum {$min} (saat ini {$totalKualitas}, akan menjadi " . ($totalKualitas - 1) . "). Tambahkan kriteria pengganti terlebih dahulu sebelum menghapus.");
            }
        }

        // Lolos validasi, lanjut soft delete
        $this->service->softDelete($kriteria);

        return redirect()
            ->route('spk-melon.kriteria.index')
            ->with('success', 'Kriteria berhasil dihapus.');
    }

    /**
     * AJAX endpoint: ambil list opsi spiSumber valid berdasarkan kategori.
     * Dipakai untuk mengisi dropdown dinamis di modal form (field Sumber Data).
     * Route: GET /spk-melon/kriteria/spi-sumber/by-kategori?kategori={kategori}
     */
    public function spiSumberByKategori(Request $request): JsonResponse
    {
        $kategori  = $request->query('kategori', '');
        $spiSumber = $this->service->getSpiSumberByKategori($kategori);

        return response()->json(['data' => $spiSumber]);
    }

    /**
     * Endpoint AJAX: ambil daftar kriteria sesuai tipe evaluasi.
     * Digunakan oleh form pembuatan sesi (SPK-02) untuk live preview.
     *
     * Aturan filter:
     * - tipe 'produktivitas' -> kategori 'produktivitas' + 'lingkungan'
     * - tipe 'kualitas'      -> kategori 'kualitas' + 'lingkungan'
     *
     * Field yang di-return mengikuti database-schema.md:
     * id, kode, nama, tipe, kategori, spiSumber, spiHitung
     */
    public function byTipeEvaluasi(string $tipe): JsonResponse
    {
        if (!in_array($tipe, ['produktivitas', 'kualitas'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tipe evaluasi tidak valid.',
                'data' => [],
            ], 422);
        }

        $kriteria = SpkMelonKriteria::where(function ($q) use ($tipe) {
            $q->where('kategori', $tipe)
                ->orWhere('kategori', 'lingkungan');
        })
            ->orderBy('kode', 'asc')
            ->get(['id', 'kode', 'nama', 'tipe', 'kategori', 'spiSumber', 'spiHitung']);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memuat {$kriteria->count()} kriteria.",
            'data' => $kriteria,
            'meta' => [
                'tipeEvaluasi'   => $tipe,
                'jumlah'         => $kriteria->count(),
                'min'            => FuzzyAhpService::MIN_KRITERIA,
                'max'            => FuzzyAhpService::MAX_KRITERIA,
                'cukupUntukSesi' => $kriteria->count() >= FuzzyAhpService::MIN_KRITERIA
                                    && $kriteria->count() <= FuzzyAhpService::MAX_KRITERIA,
                'statusKriteria' => match (true) {
                    $kriteria->count() < FuzzyAhpService::MIN_KRITERIA => 'kurang',
                    $kriteria->count() > FuzzyAhpService::MAX_KRITERIA => 'berlebih',
                    default => 'cukup',
                },
            ],
        ]);
    }
}

