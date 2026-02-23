@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
{{-- Welcome Card --}}
<x-card class="!bg-gradient-to-r !from-[var(--color-primary)] !to-[var(--color-primary-dark)] !text-white mb-6">
    <h2 class="text-xl font-bold mb-1">Selamat Datang, {{ $user['name'] ?? 'User' }}! 👋</h2>
    <p class="text-sm opacity-90">Sistem Pendukung Keputusan untuk pertanian cerdas. Pantau, analisis, dan kelola keputusan pertanian Anda.</p>
</x-card>

{{-- Quick Info Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <x-card>
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-[var(--color-primary-lighter)] flex items-center justify-center text-2xl shrink-0">
                👤
            </div>
            <div>
                <div class="text-[var(--color-gray-400)] text-[13px]">Role Anda</div>
                <div class="text-lg font-bold text-[var(--color-gray-900)]">{{ ucfirst($user['role'] ?? '-') }}</div>
            </div>
        </div>
    </x-card>

    <x-card>
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-[var(--color-amber-light)] flex items-center justify-center text-2xl shrink-0">
                📧
            </div>
            <div class="min-w-0">
                <div class="text-[var(--color-gray-400)] text-[13px]">Email</div>
                <div class="text-[15px] font-semibold text-[var(--color-gray-900)] truncate">{{ $user['email'] ?? '-' }}</div>
            </div>
        </div>
    </x-card>

    <x-card>
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-[var(--color-primary-lighter)] flex items-center justify-center text-2xl shrink-0">
                🕐
            </div>
            <div>
                <div class="text-[var(--color-gray-400)] text-[13px]">Login Sejak</div>
                <div class="text-[15px] font-semibold text-[var(--color-gray-900)]">{{ session('logged_in_at', '-') }}</div>
            </div>
        </div>
    </x-card>
</div>

{{-- Placeholder for Future Features --}}
<x-card>
    <div class="text-center py-8">
        <div class="text-[64px] mb-4">🌱</div>
        <h3 class="text-lg font-bold text-[var(--color-gray-900)] mb-2">Fitur SPK Akan Hadir</h3>
        <p class="text-sm text-[var(--color-gray-400)]">Modul analisis dan pendukung keputusan pertanian sedang dikembangkan. Tetap pantau perkembangannya!</p>
    </div>
</x-card>
@endsection
