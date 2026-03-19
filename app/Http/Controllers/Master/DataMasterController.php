<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;

class DataMasterController extends Controller
{
    /**
     * DASH-02: Halaman Data Master Operasional (Read-Only)
     * Menampilkan daftar user dan blok kebun dalam mode baca saja.
     */
    public function index()
    {
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
            'users',
            'blokKebun',
            'roleOptions',
            'jenisBudidayaOptions'
        ));
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
                // TODO: [DASH-02] Replace with actual query JOIN spk_melon_ranking
                'peringkat_terakhir' => 1,
                'skor_terakhir' => 0.8542,
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
                'peringkat_terakhir' => 3,
                'skor_terakhir' => 0.6925,
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
                'peringkat_terakhir' => 5,
                'skor_terakhir' => 0.4120,
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
                'peringkat_terakhir' => 2,
                'skor_terakhir' => 0.7831,
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
                'peringkat_terakhir' => null,
                'skor_terakhir' => null,
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
