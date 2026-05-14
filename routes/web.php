<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Iot\IotController;
use App\Http\Controllers\Peternakan\PeternakanController;
use App\Http\Controllers\Perkebunan\PerkebunanController;
use App\Http\Controllers\Master\DataMasterController;
use App\Http\Controllers\Monitoring\PlantMonitoringController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\SPKMelon\KonsistensiController;
use App\Http\Controllers\SPKMelon\KriteriaController;
use App\Http\Controllers\SPKMelon\PerbandinganController;
use App\Http\Controllers\SPKMelon\SesiPenilaianController;
use App\Http\Controllers\SPKMelon\BobotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Route structure:
| 1. Root → redirect ke dashboard (jika login) atau login (jika guest)
| 2. Guest routes → login (protected: guest.api middleware)
| 3. Auth routes → dashboard, profil, logout (protected: auth.api middleware)
|
*/

// Root redirect
Route::get('/', function () {
    if (session()->has('api_token')) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// Guest routes — hanya bisa diakses kalau BELUM login
Route::middleware('guest.api')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

// Auth routes — hanya bisa diakses jika berhasil login
/*
semua user dengan role yang berbeda tetap bisa mengakses semua fitur.
Dan kondisi yang dibuat saat ini hanya user yang sudah berhasil login saja,
bukan spesifik ke role tertentu
*/
Route::middleware('auth.api')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Peternakan
    Route::get('/peternakan', [PeternakanController::class, 'index'])->name('peternakan');

    // IoT Management
    Route::prefix('iot')->group(function () {
        Route::get('/', [IotController::class, 'dashboard'])->name('iot.dashboard');
        Route::get('/devices', [IotController::class, 'devices'])->name('iot.devices');
        Route::get('/config', [IotController::class, 'config'])->name('iot.config');
        Route::get('/monitoring', [IotController::class, 'monitoring'])->name('iot.monitoring');
    });
    // Perkebunan
    Route::get('/perkebunan', [PerkebunanController::class, 'index'])->name('perkebunan.index');

    // Data Master (DASH-02)
    Route::get('/data-master', [DataMasterController::class, 'index'])->name('data-master.index');

    // Plant Monitoring (DASH-03)
    Route::get('/plant-monitoring', [PlantMonitoringController::class, 'index'])->name('plant-monitoring.index');

    // Profil
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // SPK Melon — Konfigurasi & Kalkulasi
    // Middleware role melindungi semua route SPK-melon.
    // Role: inventor (pakar), admin, pjawab (PJ RFC), petugas (view-only).
    Route::middleware(['role:inventor,admin,pjawab,petugas'])->prefix('spk-melon')->name('spk-melon.')->group(function () {
        // SPK-01: Kriteria
        Route::prefix('kriteria')->name('kriteria.')->group(function () {
            Route::get('/', [KriteriaController::class, 'index'])->name('index');
            // AJAX endpoint: diletakkan sebelum /{kriteria} agar tidak di-resolve sebagai model binding
            Route::get('/spi-sumber/by-kategori', [KriteriaController::class, 'spiSumberByKategori'])->name('spi-sumber.by-kategori');
            // AJAX endpoint SPK-02: kriteria by tipe evaluasi (untuk live preview di modal create sesi)
            Route::get('/tipe-evaluasi/{tipe}', [KriteriaController::class, 'byTipeEvaluasi'])
                ->name('by-tipe-evaluasi')
                ->whereIn('tipe', ['produktivitas', 'kualitas']);
            Route::post('/', [KriteriaController::class, 'store'])->name('store');
            Route::put('/{kriteria}', [KriteriaController::class, 'update'])->name('update');
            Route::delete('/{kriteria}', [KriteriaController::class, 'destroy'])->name('destroy');
        });

        // SPK-02: Sesi Penilaian
        Route::prefix('sesi-penilaian')->name('sesi-penilaian.')->group(function () {
            Route::get('/', [SesiPenilaianController::class, 'index'])->name('index');
            Route::post('/', [SesiPenilaianController::class, 'store'])->name('store');
            Route::get('/{id}', [SesiPenilaianController::class, 'show'])->name('show');
            Route::delete('/{id}', [SesiPenilaianController::class, 'destroy'])->name('destroy');

            // SPK-03: Perbandingan Berpasangan (Pairwise Comparison)
            Route::get('/{id}/perbandingan', [PerbandinganController::class, 'edit'])
                ->name('perbandingan.edit');
            Route::put('/{id}/perbandingan', [PerbandinganController::class, 'update'])
                ->name('perbandingan.update');

            // SPK-04: Validasi Consistency Ratio
            Route::get('/{id}/validasi-konsistensi', [KonsistensiController::class, 'show'])
                ->name('validasi-konsistensi.show');
            Route::post('/{id}/validasi-konsistensi/hitung', [KonsistensiController::class, 'calculate'])
                ->name('validasi-konsistensi.calculate');

            // SPK-05: Kalkulasi Bobot Fuzzy AHP
            Route::get('/{id}/bobot-kriteria', [BobotController::class, 'show'])
                ->name('bobot-kriteria.show');
            Route::post('/{id}/bobot-kriteria/hitung', [BobotController::class, 'calculate'])
                ->name('bobot-kriteria.calculate');
        });

    });
});

