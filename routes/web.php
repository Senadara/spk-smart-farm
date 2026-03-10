<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\DashboardController;
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

// Auth routes — hanya bisa diakses jika berhasil login
Route::middleware('auth.api')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Peternakan
    Route::get('/peternakan', [PeternakanController::class, 'index'])->name('peternakan');

    // Perkebunan
    Route::get('/perkebunan', [PerkebunanController::class, 'index'])->name('perkebunan.index');

    // Data Master (DASH-02)
    Route::get('/data-master', [DataMasterController::class, 'index'])->name('data-master.index');

    // Profil
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
    