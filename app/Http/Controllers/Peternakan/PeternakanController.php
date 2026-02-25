<?php

namespace App\Http\Controllers\Peternakan;

// use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\WeatherService;

class PeternakanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
{
    $today = now()->format('Y-m-d');

    // 1. Population Summary
    $populasi = [
        'total_ayam' => 12000,
        'ayam_mati' => 120,
        'ayam_afkir' => 50,
        'persentase_mortalitas' => 1.4,
    ];

    // 2. Performance Index
    $performa = [
        'fcr' => 1.75,
        'hdp' => 92.5,
        'hhep' => 88.3,
    ];

    // 3. Chart Data
    $chartData = [
        'labels' => ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
        'hdp' => [90, 91, 93, 92, 94, 95, 92],
        'hhep' => [85, 86, 87, 88, 89, 90, 88],
    ];

    // 4. Weather (Dummy BMKG)
    $cuaca = [
        'lokasi' => 'Bandung',
        'suhu' => 28,
        'kelembapan' => 75,
        'kondisi' => 'Cerah Berawan',
        'angin' => '12 km/h',
    ];

    // 5. Alerts
    $alerts = [
        [
            'type' => 'warning',
            'title' => 'Suhu kandang 3 melebihi batas normal',
            'message' => 'Suhu kandang 3 melebihi batas normal',
        ],
        [
            'type' => 'danger',
            'title' => 'Mortalitas kandang 1 meningkat',
            'message' => 'Mortalitas kandang 1 meningkat',
        ],
    ];

    // 6. Daily Activities
$aktivitasHarian = [
    'laporan' => [
        'value' => 8,
        'total' => 10,
        'pending_text' => '2 aktivitas kandang belum dilaporkan hari ini'
    ],
    'pakan' => [
        'status' => 'normal',
        'value' => 450,
        'target' => 500
    ],
];

    // 7. Kandang List
    $kandangList = [
        [
            'id' => 1,
            'name' => 'Kandang A',
            'color' => '#10B981',
            'hdpVisible' => true,
            'hhepVisible' => true,
        ],
        [
            'id' => 2,
            'name' => 'Kandang B',
            'color' => '#3B82F6',
            'hdpVisible' => true,
            'hhepVisible' => true,
        ],
    ];

    // Optional (jika view masih butuh $cages)
    $cages = collect($kandangList);

    // 8. Notifications
    $notifications = collect([
        [
            'id' => 1,
            'title' => 'Suhu Tinggi',
            'message' => 'Suhu kandang 3 mencapai 35°C',
            'type' => 'warning',
            'read_at' => null,
            'created_at' => '5 menit lalu',
        ],
        [
            'id' => 2,
            'title' => 'Produksi Telur Stabil',
            'message' => 'HDP meningkat 2% hari ini',
            'type' => 'success',
            'read_at' => null,
            'created_at' => '1 jam lalu',
        ],
    ]);

    return view('peternakan.dashboard', compact(
        'populasi',
        'performa',
        'chartData',
        'cuaca',
        'alerts',
        'aktivitasHarian',
        'kandangList',
        'cages',
        'notifications'
    ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
