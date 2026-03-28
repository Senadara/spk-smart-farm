@extends('layouts.app')

@section('title', 'Daftar Pengaturan')
@section('breadcrumb', 'Pengaturan')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Pengaturan Sistem</h1>
        <p class="text-sm text-gray-500 mt-1">Kelola konfigurasi dasar peternakan, daftar perangkat, dan aturan IoT.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

        {{-- Parameter Master --}}
        <a href="{{ route('data-master.index') }}" class="group block bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md hover:border-emerald-300 transition-all">
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center mb-4 group-hover:bg-blue-100 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 group-hover:text-emerald-700 transition-colors">Data Master</h3>
            <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                Kelola daftar kandang, zona wilayah, jenis pakan, jadwal vaksin, dan parameter baku lainnya.
            </p>
        </a>

        {{-- IoT Devices --}}
        <a href="{{ route('iot.devices') }}" class="group block bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md hover:border-emerald-300 transition-all">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mb-4 group-hover:bg-emerald-100 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 group-hover:text-emerald-700 transition-colors">Perangkat IoT</h3>
            <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                Pendaftaran node sensor, microcontroller, alat ukur pakan, dan actuator kipas di tiap kandang.
            </p>
        </a>

        {{-- IoT Configuration --}}
        <a href="{{ route('iot.config') }}" class="group block bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md hover:border-emerald-300 transition-all">
            <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center mb-4 group-hover:bg-purple-100 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 group-hover:text-emerald-700 transition-colors">Konfigurasi IoT</h3>
            <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                Tentukan batas threshold sensor (suhu/amonia), frekuensi pengiriman data, dan aturan aktuasi udara.
            </p>
        </a>

    </div>
</div>
@endsection
