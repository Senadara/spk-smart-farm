@extends('layouts.app')

@section('title', 'Profil Saya')
@section('breadcrumb', 'Profil')

@section('content')
<h1 class="text-xl font-bold text-[var(--color-gray-900)] mb-6">Profil Saya</h1>

{{-- Profile Card --}}
<x-card class="mb-6">
    {{-- Profile Header --}}
    <div class="flex items-center gap-5 pb-5 border-b border-[var(--color-gray-100)]">
        @if (!empty($user['avatar']))
            <img src="{{ $user['avatar'] }}" alt="Avatar"
                 class="w-20 h-20 rounded-full object-cover ring-4 ring-[var(--color-primary-light)] shrink-0">
        @else
            <div class="w-20 h-20 rounded-full bg-[var(--color-primary-light)] flex items-center justify-center
                        font-bold text-4xl text-[var(--color-primary-dark)] shrink-0">
                {{ strtoupper(substr($user['name'] ?? 'U', 0, 1)) }}
            </div>
        @endif
        <div>
            <h2 class="text-xl font-bold text-[var(--color-gray-900)] mb-1">{{ $user['name'] ?? '-' }}</h2>
            <x-badge color="green">{{ ucfirst($user['role'] ?? '-') }}</x-badge>
        </div>
    </div>

    {{-- Profile Details --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-5">
        <div>
            <span class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-gray-400)] mb-1">Email</span>
            <span class="text-sm font-medium text-[var(--color-gray-900)]">{{ $user['email'] ?? '-' }}</span>
        </div>
        <div>
            <span class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-gray-400)] mb-1">Telepon</span>
            <span class="text-sm font-medium text-[var(--color-gray-900)]">{{ $user['phone'] ?? '-' }}</span>
        </div>
        <div>
            <span class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-gray-400)] mb-1">Role</span>
            <span class="text-sm font-medium text-[var(--color-gray-900)]">{{ ucfirst($user['role'] ?? '-') }}</span>
        </div>
        <div>
            <span class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-gray-400)] mb-1">Login Sejak</span>
            <span class="text-sm font-medium text-[var(--color-gray-900)]">{{ session('logged_in_at', '-') }}</span>
        </div>
    </div>
</x-card>

@if(($user['role'] ?? '') !== 'supplier')
<x-card class="mb-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h3 class="text-base font-bold text-[var(--color-gray-900)]">Lokasi Operasional Peternakan</h3>
            <p class="mt-1 text-sm text-[var(--color-gray-500)]">Lokasi ini menjadi titik asal perhitungan jarak supplier pada SPK AHP-SAW.</p>
        </div>
        @if($farmProfile?->hasCoordinates())
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Koordinat aktif</span>
        @else
            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Lengkapi koordinat</span>
        @endif
    </div>

    <form method="POST" action="{{ route('profile.farm-location') }}" class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
        @csrf
        @method('PATCH')

        <div>
            <label for="farm_name" class="mb-1 block text-sm font-semibold text-[var(--color-gray-700)]">Nama peternakan</label>
            <input id="farm_name" name="farm_name" value="{{ old('farm_name', $farmProfile?->farm_name) }}"
                placeholder="Contoh: SmartFarm Layer Malang"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
            @error('farm_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="address" class="mb-1 block text-sm font-semibold text-[var(--color-gray-700)]">Alamat utama</label>
            <input id="address" name="address" value="{{ old('address', $farmProfile?->address) }}" required
                placeholder="Alamat operasional peternakan"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
            @error('address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="latitude" class="mb-1 block text-sm font-semibold text-[var(--color-gray-700)]">Latitude</label>
            <input id="latitude" name="latitude" type="number" step="0.0000001" value="{{ old('latitude', $farmProfile?->latitude) }}"
                placeholder="-7.9666204"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
            @error('latitude')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="longitude" class="mb-1 block text-sm font-semibold text-[var(--color-gray-700)]">Longitude</label>
            <input id="longitude" name="longitude" type="number" step="0.0000001" value="{{ old('longitude', $farmProfile?->longitude) }}"
                placeholder="112.6326321"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
            @error('longitude')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="md:col-span-2 flex flex-col gap-3 rounded-lg bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-gray-500">Ambil koordinat dari Google Maps dengan klik kanan pada lokasi peternakan, lalu salin angka latitude dan longitude.</p>
            <button class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Simpan Lokasi</button>
        </div>
    </form>
</x-card>
@endif

{{-- Login History --}}
<x-card>
    <h3 class="text-base font-bold text-[var(--color-gray-900)] mb-4">Riwayat Login</h3>

    @if ($loginHistories->isEmpty())
        <div class="text-center py-8">
            <div class="text-5xl mb-3">📋</div>
            <p class="text-sm text-[var(--color-gray-400)]">Belum ada riwayat login.</p>
        </div>
    @else
        <div class="overflow-x-auto -mx-5">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu Login</th>
                        <th>IP Address</th>
                        <th>Browser / Perangkat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($loginHistories as $history)
                    <tr>
                        <td class="whitespace-nowrap">{{ $history->createdAt->format('d M Y, H:i') }}</td>
                        <td>
                            <code class="bg-[var(--color-gray-100)] px-2 py-0.5 rounded text-[13px] font-mono">
                                {{ $history->ipAddress ?? '-' }}
                            </code>
                        </td>
                        <td class="max-w-[300px] truncate">
                            {{ Str::limit($history->userAgent, 80) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>
@endsection
