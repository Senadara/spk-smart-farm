<?php

namespace App\Http\Controllers\SPKMelon;

use App\Http\Controllers\Controller;
use App\Http\Requests\SPKMelon\StoreKriteriaRequest;
use App\Http\Requests\SPKMelon\UpdateKriteriaRequest;
use App\Models\SPKMelon\SpkMelonKriteria;
use App\Services\SPKMelon\KriteriaService;
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

        return view('spk-melon.kriteria.index', [
            'daftarKriteria' => $daftarKriteria,
            'filterKategori' => $filterKategori,
            'nextKode'       => $this->service->generateNextKode(),
        ]);
    }

    /**
     * Simpan kriteria baru (dari modal form tambah).
     * Route: POST /spk-melon/kriteria
     */
    public function store(StoreKriteriaRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

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
}
