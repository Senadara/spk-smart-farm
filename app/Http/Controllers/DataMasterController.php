<?php

namespace App\Http\Controllers;

use App\Services\LivestockMasterConfigService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DataMasterController extends Controller
{
    public function __construct(
        protected LivestockMasterConfigService $livestockMasterConfigService
    ) {}

    /**
     * Data Master ternak membaca jenis ternak dari mobile, lalu web menentukan
     * parameter lingkungan dan fungsi produktivitas yang boleh dipakai IoT/SPK.
     */
    public function index(Request $request)
    {
        $masterOverview = $this->livestockMasterConfigService->overview($request->query('jenis_budidaya_id'));
        $users = $this->getDummyUsers();
        $blokKebun = $this->getDummyBlokKebun();

        $roleOptions = [
            'pjawab' => 'Penanggung Jawab',
            'inventor' => 'Pengelola RFC',
            'petugas' => 'Petugas Perkebunan',
            'admin' => 'Administrator',
        ];
        $jenisBudidayaOptions = $this->getDummyJenisBudidaya();

        return view('data-master.index', compact(
            'masterOverview',
            'users',
            'blokKebun',
            'roleOptions',
            'jenisBudidayaOptions'
        ));
    }

    public function storeLivestockMaster(Request $request)
    {
        $validated = $request->validate([
            'jenis_budidaya_id' => 'required|string|exists:jenisBudidaya,id',
            'commodity_id' => 'nullable|string|exists:komoditas,id',
            'notes' => 'nullable|string|max:1000',
            'environment_parameters' => 'required|array|min:1',
            'environment_parameters.*.parameter_code' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_\\-\\s]+$/'],
            'environment_parameters.*.parameter_name' => 'nullable|string|max:150',
            'environment_parameters.*.unit' => 'nullable|string|max:30',
            'environment_parameters.*.min_value' => 'nullable|numeric',
            'environment_parameters.*.max_value' => 'nullable|numeric',
            'environment_parameters.*.fallback_value' => 'nullable|numeric',
            'environment_parameters.*.stale_minutes' => 'nullable|integer|min:1|max:10080',
            'environment_parameters.*.required_for_iot' => 'nullable|boolean',
            'environment_parameters.*.required_for_fuzzy' => 'nullable|boolean',
            'productivity_function_ids' => 'required|array|min:1',
            'productivity_function_ids.*' => 'string|exists:livestock_productivity_functions,id',
        ], [
            'environment_parameters.required' => 'Minimal satu parameter lingkungan wajib diisi.',
            'environment_parameters.min' => 'Minimal satu parameter lingkungan wajib diisi.',
            'productivity_function_ids.required' => 'Pilih minimal satu fungsi produktivitas.',
            'productivity_function_ids.min' => 'Pilih minimal satu fungsi produktivitas.',
            'environment_parameters.*.parameter_code.regex' => 'Kode parameter hanya boleh huruf, angka, spasi, underscore, atau dash.',
        ]);

        $filledEnvironmentRows = collect($validated['environment_parameters'])
            ->filter(fn ($row) => trim((string) ($row['parameter_code'] ?? '')) !== '' || trim((string) ($row['parameter_name'] ?? '')) !== '');

        if ($filledEnvironmentRows->isEmpty()) {
            throw ValidationException::withMessages([
                'environment_parameters' => 'Minimal satu parameter lingkungan wajib diisi.',
            ]);
        }

        foreach ($filledEnvironmentRows as $index => $row) {
            if (trim((string) ($row['parameter_code'] ?? '')) === '' || trim((string) ($row['parameter_name'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "environment_parameters.{$index}.parameter_code" => 'Kode dan nama parameter wajib diisi pada baris yang aktif.',
                ]);
            }

            if (($row['min_value'] ?? null) !== null && ($row['max_value'] ?? null) !== null && (float) $row['min_value'] >= (float) $row['max_value']) {
                throw ValidationException::withMessages([
                    "environment_parameters.{$index}.min_value" => 'Nilai minimum harus lebih kecil dari maksimum.',
                ]);
            }
        }

        $validated['environment_parameters'] = $filledEnvironmentRows->values()->all();
        $validated['configured_by'] = data_get(session('user'), 'id');

        $this->livestockMasterConfigService->saveConfiguration($validated);

        return redirect()
            ->route('data-master.index', ['jenis_budidaya_id' => $validated['jenis_budidaya_id']])
            ->with('success', 'Konfigurasi Data Master ternak berhasil disimpan.');
    }

    /**
     * TODO: [DASH-02] Ganti dengan query Eloquent:
     * User::whereIn('role', ['pjawab', 'inventor', 'petugas', 'admin'])
     *     ->where('isDeleted', 0)
     *     ->select('id', 'nama', 'email', 'role', 'status', 'createdAt')
     *     ->orderBy('role')
     *     ->get();
     */
    private function getDummyUsers(): array
    {
        return [
            [
                'id' => 'usr-001',
                'nama' => 'Dr. Ahmad Suryadi',
                'email' => 'ahmad.suryadi@rfc.telkomuniversity.ac.id',
                'role' => 'pjawab',
                'status' => 1,
                'createdAt' => '2024-08-15 09:00:00',
            ],
            [
                'id' => 'usr-002',
                'nama' => 'Siti Nurhaliza',
                'email' => 'siti.nurhaliza@rfc.telkomuniversity.ac.id',
                'role' => 'inventor',
                'status' => 1,
                'createdAt' => '2024-08-15 09:30:00',
            ],
            [
                'id' => 'usr-003',
                'nama' => 'Budi Santoso',
                'email' => 'budi.santoso@rfc.telkomuniversity.ac.id',
                'role' => 'petugas',
                'status' => 1,
                'createdAt' => '2024-09-01 08:00:00',
            ],
            [
                'id' => 'usr-004',
                'nama' => 'Rina Wulandari',
                'email' => 'rina.wulandari@rfc.telkomuniversity.ac.id',
                'role' => 'petugas',
                'status' => 1,
                'createdAt' => '2024-09-01 08:15:00',
            ],
            [
                'id' => 'usr-005',
                'nama' => 'Hendro Prasetyo',
                'email' => 'hendro.prasetyo@rfc.telkomuniversity.ac.id',
                'role' => 'inventor',
                'status' => 0,
                'createdAt' => '2024-10-05 10:00:00',
            ],
            [
                'id' => 'usr-006',
                'nama' => 'Admin Sistem',
                'email' => 'admin@rfc.telkomuniversity.ac.id',
                'role' => 'admin',
                'status' => 1,
                'createdAt' => '2024-08-01 00:00:00',
            ],
        ];
    }

    /**
     * TODO: [DASH-02] Ganti dengan query Eloquent:
     * DB::table('unitBudidaya')
     *     ->join('jenisBudidaya', 'unitBudidaya.JenisBudidayaId', '=', 'jenisBudidaya.id')
     *     ->where('jenisBudidaya.tipe', 'tumbuhan')
     *     ->where('unitBudidaya.isDeleted', 0)
     *     ->where('jenisBudidaya.isDeleted', 0)
     *     ->select(
     *         'unitBudidaya.id',
     *         'unitBudidaya.nama',
     *         'unitBudidaya.lokasi',
     *         'unitBudidaya.luas',
     *         'unitBudidaya.jumlah as kapasitas',
     *         'unitBudidaya.status',
     *         'unitBudidaya.deskripsi',
     *         'jenisBudidaya.nama as jenisBudidaya',
     *         'jenisBudidaya.latin as namaLatin'
     *     )
     *     ->orderBy('unitBudidaya.nama')
     *     ->get()
     *     ->map(fn($item) => (array) $item)
     *     ->toArray();
     */
    private function getDummyBlokKebun(): array
    {
        return [
            [
                'id' => 'ub-001',
                'nama' => 'Greenhouse A',
                'lokasi' => 'Rooftop Gedung A - Lantai 5',
                'luas' => 120.5,
                'kapasitas' => 200,
                'status' => 1,
                'deskripsi' => 'Greenhouse utama untuk budidaya melon premium',
                'jenisBudidaya' => 'Melon',
                'namaLatin' => 'Cucumis melo L.',
            ],
            [
                'id' => 'ub-002',
                'nama' => 'Greenhouse B',
                'lokasi' => 'Rooftop Gedung A - Lantai 5',
                'luas' => 95.0,
                'kapasitas' => 150,
                'status' => 1,
                'deskripsi' => 'Greenhouse pendukung untuk varietas melon golden',
                'jenisBudidaya' => 'Melon',
                'namaLatin' => 'Cucumis melo L.',
            ],
            [
                'id' => 'ub-003',
                'nama' => 'Greenhouse C',
                'lokasi' => 'Rooftop Gedung B - Lantai 4',
                'luas' => 80.0,
                'kapasitas' => 120,
                'status' => 1,
                'deskripsi' => 'Greenhouse eksperimen untuk uji coba varietas baru',
                'jenisBudidaya' => 'Melon',
                'namaLatin' => 'Cucumis melo L.',
            ],
            [
                'id' => 'ub-004',
                'nama' => 'Greenhouse D',
                'lokasi' => 'Rooftop Gedung B - Lantai 4',
                'luas' => 60.0,
                'kapasitas' => 80,
                'status' => 0,
                'deskripsi' => 'Greenhouse sedang dalam masa renovasi',
                'jenisBudidaya' => 'Melon',
                'namaLatin' => 'Cucumis melo L.',
            ],
            [
                'id' => 'ub-005',
                'nama' => 'Plot Pakcoy Hidroponik',
                'lokasi' => 'Rooftop Gedung C - Lantai 3',
                'luas' => 45.0,
                'kapasitas' => 500,
                'status' => 1,
                'deskripsi' => 'Plot sayuran hidroponik pendamping',
                'jenisBudidaya' => 'Pakcoy',
                'namaLatin' => 'Brassica rapa subsp. chinensis',
            ],
        ];
    }

    /**
     * TODO: [DASH-02] Ganti dengan query Eloquent:
     * DB::table('jenisBudidaya')
     *     ->where('tipe', 'tumbuhan')
     *     ->where('isDeleted', 0)
     *     ->pluck('nama', 'id')
     *     ->toArray();
     */
    private function getDummyJenisBudidaya(): array
    {
        return [
            'jb-001' => 'Melon',
            'jb-002' => 'Pakcoy',
        ];
    }
}
