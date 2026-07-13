<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\DataMasterController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Iot\IotController;
use App\Http\Controllers\Perkebunan\PerkebunanController;
use App\Http\Controllers\Peternakan\PeternakanController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Supplier\SupplierPanelController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Route structure:
| 1. Root -> redirect ke dashboard (jika login) atau login (jika guest)
| 2. Guest routes -> login (protected: guest.api middleware)
| 3. Auth routes -> dashboard, profil, logout (protected: auth.api middleware)
|
*/

// Root redirect
Route::get('/', function () {
    if (session()->has('api_token')) {
        if (session('user.role') === 'supplier') {
            return redirect()->route('supplier.dashboard');
        }

        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

// Guest routes - hanya bisa diakses kalau BELUM login
Route::middleware('guest.api')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

// Webhook IoT - HARUS di luar auth.api agar device IoT bisa kirim data tanpa login
Route::post('/iot/webhook/{deviceCode}', [IotController::class, 'handleWebhook'])
    ->name('iot.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Auth routes - hanya bisa diakses jika berhasil login
Route::middleware(['auth.api', 'role:pjawab,petugas,owner,admin,inventor,penjual,user'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Peternakan
    Route::get('/peternakan', [PeternakanController::class, 'index'])->name('peternakan');
    Route::post('/peternakan/evaluate-all', [PeternakanController::class, 'evaluateAll'])->name('peternakan.evaluate-all');
    Route::get('/peternakan/{id}/settlement', [PeternakanController::class, 'settlement'])->name('peternakan.settlement');
    Route::get('/peternakan/{id}/export-productivity', [PeternakanController::class, 'exportProductivity'])->name('peternakan.export-productivity');
    Route::get('/peternakan/{id}', [PeternakanController::class, 'show'])->name('peternakan.show');

    // Analisa SPK
    Route::get('/spk-analysis', [\App\Http\Controllers\Spk\SpkDashboardController::class, 'index'])->name('spk.dashboard');
    Route::post('/spk-analysis/evaluate', [\App\Http\Controllers\Spk\SpkDashboardController::class, 'evaluate'])->name('spk.dashboard.evaluate');

    // Fuzzy Mamdani Engine
    Route::prefix('spk-fuzzy')->group(function () {
        Route::post('/process', [\App\Http\Controllers\Spk\FuzzyController::class, 'processFuzzy'])->name('spk.fuzzy.process');
        Route::get('/history', [\App\Http\Controllers\Spk\FuzzyController::class, 'getHistory'])->name('spk.fuzzy.history');
        Route::get('/history/{id}', [\App\Http\Controllers\Spk\FuzzyController::class, 'getHistoryDetail'])->name('spk.fuzzy.history.detail');
        Route::get('/config', [\App\Http\Controllers\Spk\FuzzyController::class, 'getConfig'])->name('spk.fuzzy.config');
    });

    // SPK Supplier Recommendations (AHP-SAW DSS)
    Route::prefix('spk-suppliers')->group(function () {
        Route::get('/', [\App\Http\Controllers\Spk\SupplierRecommendationController::class, 'index'])->name('spk.suppliers.index');
        Route::get('/products', [\App\Http\Controllers\Spk\SupplierRecommendationController::class, 'products'])->name('spk.suppliers.products');
        Route::get('/dss/config', [\App\Http\Controllers\Spk\SpkSupplierDssController::class, 'config'])->name('spk.suppliers.dss.config');
        Route::post('/dss/config', [\App\Http\Controllers\Spk\SpkSupplierDssController::class, 'storePerbandingan'])->name('spk.suppliers.dss.config.store');
        Route::get('/dss/dashboard', [\App\Http\Controllers\Spk\SpkSupplierDssController::class, 'dashboard'])->name('spk.suppliers.dss.dashboard');
        Route::get('/dss/api/evaluation/{produkId}', [\App\Http\Controllers\Spk\SpkSupplierDssController::class, 'apiEvaluation'])->name('spk.suppliers.dss.api.evaluation');
        Route::get('/dss/api/rankings/{produkId}', [\App\Http\Controllers\Spk\SpkSupplierDssController::class, 'apiRankings'])->name('spk.suppliers.dss.api.rankings');
        Route::get('/dss/api/weights', [\App\Http\Controllers\Spk\SpkSupplierDssController::class, 'apiWeights'])->name('spk.suppliers.dss.api.weights');
        Route::get('/dss/api/insights', [\App\Http\Controllers\Spk\SpkSupplierDssController::class, 'apiInsights'])->name('spk.suppliers.dss.api.insights');
        Route::get('/orders', [\App\Http\Controllers\Spk\SupplierRecommendationController::class, 'orders'])->name('spk.suppliers.orders.index');
        Route::patch('/orders/{order}/cancel', [\App\Http\Controllers\Spk\SupplierRecommendationController::class, 'cancelOrder'])->name('spk.suppliers.orders.cancel');
        Route::post('/cart', [\App\Http\Controllers\Spk\SupplierRecommendationController::class, 'addToCart'])->name('spk.suppliers.cart.add');
        Route::delete('/cart/{product}', [\App\Http\Controllers\Spk\SupplierRecommendationController::class, 'removeFromCart'])->name('spk.suppliers.cart.remove');
        Route::post('/cart/checkout', [\App\Http\Controllers\Spk\SupplierRecommendationController::class, 'checkoutCart'])->name('spk.suppliers.cart.checkout');
        Route::post('/{id}/orders', [\App\Http\Controllers\Spk\SupplierRecommendationController::class, 'storeOrder'])->name('spk.suppliers.orders.store')->whereNumber('id');
        Route::get('/{id}', [\App\Http\Controllers\Spk\SupplierRecommendationController::class, 'show'])->name('spk.suppliers.show')->whereNumber('id');
    });

    // Penugasan & Laporan Tindakan SPK
    Route::prefix('penugasan')->group(function () {
        Route::get('/', [\App\Http\Controllers\Spk\SpkTaskController::class, 'index'])->name('spk.tasks.index');
        Route::post('/', [\App\Http\Controllers\Spk\SpkTaskController::class, 'store'])->name('spk.tasks.store');
        Route::get('/{id}', [\App\Http\Controllers\Spk\SpkTaskController::class, 'show'])->name('spk.tasks.show');
        Route::put('/{id}', [\App\Http\Controllers\Spk\SpkTaskController::class, 'update'])->name('spk.tasks.update');
        Route::delete('/{id}', [\App\Http\Controllers\Spk\SpkTaskController::class, 'destroy'])->name('spk.tasks.destroy');
        Route::patch('/{id}/status', [\App\Http\Controllers\Spk\SpkTaskController::class, 'updateStatus'])->name('spk.tasks.status');
        Route::post('/{id}/report', [\App\Http\Controllers\Spk\SpkTaskController::class, 'submitReport'])->name('spk.tasks.report');
    });

    // IoT Management
    Route::prefix('iot')->group(function () {
        Route::get('/', [IotController::class, 'dashboard'])->name('iot.dashboard');
        Route::get('/devices', [IotController::class, 'devices'])->name('iot.devices');
        Route::get('/config', [IotController::class, 'config'])->name('iot.config');
        Route::get('/monitoring', [IotController::class, 'monitoring'])->name('iot.monitoring');

        // CRUD Endpoints - Devices
        Route::post('/devices', [IotController::class, 'storeDevice'])->name('iot.devices.store');
        Route::put('/devices/{id}', [IotController::class, 'updateDevice'])->name('iot.devices.update');
        Route::delete('/devices/{id}', [IotController::class, 'destroyDevice'])->name('iot.devices.destroy');

        // CRUD Endpoints - Mappings
        Route::post('/mappings', [IotController::class, 'storeMapping'])->name('iot.mappings.store');
        Route::put('/mappings/{id}', [IotController::class, 'updateMapping'])->name('iot.mappings.update');
        Route::delete('/mappings/{id}', [IotController::class, 'destroyMapping'])->name('iot.mappings.destroy');

        // CRUD Endpoints - Protocols
        Route::post('/protocols', [IotController::class, 'storeProtocol'])->name('iot.protocols.store');
        Route::put('/protocols/{id}', [IotController::class, 'updateProtocol'])->name('iot.protocols.update');
        Route::delete('/protocols/{id}', [IotController::class, 'destroyProtocol'])->name('iot.protocols.destroy');

        // CRUD Endpoints - Connections
        Route::post('/connections', [IotController::class, 'storeConnection'])->name('iot.connections.store');
        Route::put('/connections/{id}', [IotController::class, 'updateConnection'])->name('iot.connections.update');
        Route::delete('/connections/{id}', [IotController::class, 'destroyConnection'])->name('iot.connections.destroy');

        // CRUD Endpoints - Parameters
        Route::post('/parameters', [IotController::class, 'storeParameter'])->name('iot.parameters.store');
        Route::put('/parameters/{id}', [IotController::class, 'updateParameter'])->name('iot.parameters.update');
        Route::delete('/parameters/{id}', [IotController::class, 'destroyParameter'])->name('iot.parameters.destroy');

        // CRUD Endpoints - Commodity Parameters
        Route::post('/commodity-params', [IotController::class, 'storeCommodityParam'])->name('iot.commodity-params.store');
        Route::put('/commodity-params/{id}', [IotController::class, 'updateCommodityParam'])->name('iot.commodity-params.update');
        Route::delete('/commodity-params/{id}', [IotController::class, 'destroyCommodityParam'])->name('iot.commodity-params.destroy');
    });

    Route::get('/perkebunan', [PerkebunanController::class, 'index'])->name('perkebunan.index');

    // Inventaris
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::post('/items', [InventoryController::class, 'store'])->name('items.store');
        Route::get('/items/{item}', [InventoryController::class, 'show'])->name('items.show');
        Route::post('/items/{item}/adjust', [InventoryController::class, 'adjust'])->name('items.adjust');
        Route::post('/items/{item}/supplier-links', [InventoryController::class, 'storeSupplierLink'])->name('items.supplier-links.store');
        Route::post('/items/{item}/restock-order', [InventoryController::class, 'orderRestock'])->name('items.restock-order');
        Route::post('/purchase-order', [InventoryController::class, 'purchaseOrder'])->name('purchase-order');
        Route::get('/analysis', [InventoryController::class, 'analysis'])->name('analysis');
    });

    // Data Master (DASH-02)
    Route::get('/data-master', [DataMasterController::class, 'index'])->name('data-master.index');

    // Pengaturan (Settings Hub)
    Route::get('/settings', [\App\Http\Controllers\Settings\SettingsController::class, 'index'])->name('settings.index');

    // Manajemen Karyawan / Petugas (Khusus Owner)
    Route::middleware('role:pjawab')->group(function () {
        Route::resource('users', UserManagementController::class)->except(['create', 'show', 'edit']);
    });

    // Konfigurasi Fuzzy Mamdani
    Route::middleware('role:pjawab')->prefix('settings/fuzzy')->group(function () {
        Route::get('/', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'index'])->name('settings.fuzzy.index');
        Route::post('/profiles', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'storeProfile'])->name('settings.fuzzy.profiles.store');
        Route::patch('/profiles/{id}/activate', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'activateProfile'])->name('settings.fuzzy.profiles.activate');
        // CRUD Variables
        Route::post('/variables', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'storeVariable'])->name('settings.fuzzy.variables.store');
        Route::put('/variables/{id}', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'updateVariable'])->name('settings.fuzzy.variables.update');
        Route::delete('/variables/{id}', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'destroyVariable'])->name('settings.fuzzy.variables.destroy');
        // CRUD Sets
        Route::post('/sets', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'storeSet'])->name('settings.fuzzy.sets.store');
        Route::put('/sets/{id}', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'updateSet'])->name('settings.fuzzy.sets.update');
        Route::delete('/sets/{id}', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'destroySet'])->name('settings.fuzzy.sets.destroy');
        // CRUD Rules
        Route::post('/rules', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'storeRule'])->name('settings.fuzzy.rules.store');
        Route::put('/rules/{id}', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'updateRule'])->name('settings.fuzzy.rules.update');
        Route::delete('/rules/{id}', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'destroyRule'])->name('settings.fuzzy.rules.destroy');
        // CRUD Input Sources
        Route::post('/sources', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'storeSource'])->name('settings.fuzzy.sources.store');
        Route::put('/sources/{id}', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'updateSource'])->name('settings.fuzzy.sources.update');
        Route::delete('/sources/{id}', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'destroySource'])->name('settings.fuzzy.sources.destroy');
        // Reset
        Route::post('/reset', [\App\Http\Controllers\Settings\FuzzyConfigController::class, 'resetToDefault'])->name('settings.fuzzy.reset');
    });

    // Supplier Management & SPK AHP-SAW Config
    Route::prefix('supplier-spk')->group(function () {
        Route::apiResource('suppliers', \App\Http\Controllers\SupplierController::class);
        Route::get('parameters', [\App\Http\Controllers\SpkParameterController::class, 'index'])->name('spk.parameters.index');
        Route::post('parameters', [\App\Http\Controllers\SpkParameterController::class, 'store'])->name('spk.parameters.store');
        Route::post('parameters/assign', [\App\Http\Controllers\SpkParameterController::class, 'assignValue'])->name('spk.parameters.assign');
        Route::post('ahp/perbandingan', [\App\Http\Controllers\SpkAHPController::class, 'storePerbandingan'])->name('spk.ahp.perbandingan');
        Route::get('recommendation/{produkId}', [\App\Http\Controllers\RecommendationController::class, 'getRanking'])->name('spk.recommendation');
        Route::get('evaluation/{produkId}', [\App\Http\Controllers\Spk\SpkSupplierDssController::class, 'apiEvaluation'])->name('spk.suppliers.evaluation');
    });

});

Route::middleware('auth.api')->group(function () {
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile');
    Route::patch('/profil/farm-location', [ProfileController::class, 'updateFarm'])->name('profile.farm-location');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('role:supplier')->prefix('supplier')->name('supplier.')->group(function () {
        Route::get('/', [SupplierPanelController::class, 'dashboard'])->name('dashboard');
        Route::get('/store', [SupplierPanelController::class, 'editStore'])->name('store.edit');
        Route::put('/store', [SupplierPanelController::class, 'updateStore'])->name('store.update');

        Route::get('/products', [SupplierPanelController::class, 'products'])->name('products.index');
        Route::post('/products', [SupplierPanelController::class, 'storeProduct'])->name('products.store');
        Route::put('/products/{product}', [SupplierPanelController::class, 'updateProduct'])->name('products.update');
        Route::delete('/products/{product}', [SupplierPanelController::class, 'destroyProduct'])->name('products.destroy');

        Route::get('/orders', [SupplierPanelController::class, 'orders'])->name('orders.index');
        Route::patch('/orders/{order}/status', [SupplierPanelController::class, 'updateOrderStatus'])
            ->name('orders.status');

        Route::get('/finance', [SupplierPanelController::class, 'finance'])->name('finance');
    });
});
