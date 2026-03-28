<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Dashboard/Hub utama untuk semua menu Pengaturan.
     */
    public function index()
    {
        return view('settings.index');
    }
}
