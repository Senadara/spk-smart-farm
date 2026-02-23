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
                        <td class="whitespace-nowrap">{{ $history->login_at->format('d M Y, H:i') }}</td>
                        <td>
                            <code class="bg-[var(--color-gray-100)] px-2 py-0.5 rounded text-[13px] font-mono">
                                {{ $history->ip_address ?? '-' }}
                            </code>
                        </td>
                        <td class="max-w-[300px] truncate">
                            {{ Str::limit($history->user_agent, 80) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>
@endsection
