<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Iot\IotController;
use App\Http\Controllers\Peternakan\PeternakanController;
use App\Http\Controllers\Perkebunan\PerkebunanController;
use App\Http\Controllers\DataMasterController;
use App\Http\Controllers\Profile\ProfileController;
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

// Webhook IoT — HARUS di luar auth.api agar device IoT bisa kirim data tanpa login
Route::post('/iot/webhook/{deviceCode}', [IotController::class, 'handleWebhook'])
    ->name('iot.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Auth routes — hanya bisa diakses jika berhasil login
Route::middleware('auth.api')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Peternakan
    Route::get('/peternakan', [PeternakanController::class, 'index'])->name('peternakan');
    Route::get('/peternakan/{id}', [PeternakanController::class, 'show'])->name('peternakan.show');

    // Analisa SPK
    Route::get('/spk-analysis', [\App\Http\Controllers\Spk\SpkDashboardController::class, 'index'])->name('spk.dashboard');
    
    // SPK Supplier Recommendations
    Route::get('/spk-suppliers', [\App\Http\Controllers\Spk\SupplierRecommendationController::class, 'index'])->name('spk.suppliers.index');
    Route::get('/spk-suppliers/products', [\App\Http\Controllers\Spk\SupplierRecommendationController::class, 'products'])->name('spk.suppliers.products');
    Route::get('/spk-suppliers/{id}', [\App\Http\Controllers\Spk\SupplierRecommendationController::class, 'show'])->name('spk.suppliers.show');

    // IoT Management
    Route::prefix('iot')->group(function () {
        Route::get('/', [IotController::class, 'dashboard'])->name('iot.dashboard');
        Route::get('/devices', [IotController::class, 'devices'])->name('iot.devices');
        Route::get('/config', [IotController::class, 'config'])->name('iot.config');
        Route::get('/monitoring', [IotController::class, 'monitoring'])->name('iot.monitoring');

        // CRUD Endpoints
        Route::post('/devices', [IotController::class, 'storeDevice'])->name('iot.devices.store');
        Route::put('/devices/{id}', [IotController::class, 'updateDevice'])->name('iot.devices.update');
        Route::delete('/devices/{id}', [IotController::class, 'destroyDevice'])->name('iot.devices.destroy');
        Route::post('/mappings', [IotController::class, 'storeMapping'])->name('iot.mappings.store');
        Route::delete('/mappings/{id}', [IotController::class, 'destroyMapping'])->name('iot.mappings.destroy');
        Route::post('/protocols', [IotController::class, 'storeProtocol'])->name('iot.protocols.store');
        Route::post('/connections', [IotController::class, 'storeConnection'])->name('iot.connections.store');
        Route::post('/parameters', [IotController::class, 'storeParameter'])->name('iot.parameters.store');
    });

    Route::get('/perkebunan', [PerkebunanController::class, 'index'])->name('perkebunan.index');

    // Inventaris
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');

    // Data Master (DASH-02)
    Route::get('/data-master', [DataMasterController::class, 'index'])->name('data-master.index');

    // Pengaturan (Settings Hub)
    Route::get('/settings', [\App\Http\Controllers\Settings\SettingsController::class, 'index'])->name('settings.index');

    // Profil
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
