<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\SpkSupplierParameterValue::observe(\App\Observers\SpkSupplierParameterValueObserver::class);
        \App\Models\SpkAhpBobot::observe(\App\Observers\SpkAhpBobotObserver::class);
        \App\Models\InventorySupplierProduk::observe(\App\Observers\InventorySupplierProdukObserver::class);
    }
}
