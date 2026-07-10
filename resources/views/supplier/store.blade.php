@extends('layouts.app')

@section('title', 'Profil Toko')
@section('breadcrumb', 'Profil Toko')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div>
        <p class="text-sm font-medium text-emerald-700 mb-1">Pengaturan Supplier</p>
        <h1 class="text-2xl font-bold text-gray-900">Profil Toko</h1>
        <p class="text-sm text-gray-500 mt-1">Informasi ini ditampilkan kepada calon pembeli dan dipakai sebagai identitas supplier.</p>
    </div>

    <form method="POST" action="{{ route('supplier.store.update') }}" enctype="multipart/form-data"
        class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        @csrf
        @method('PUT')

        <div class="p-5 md:p-7 border-b border-gray-100 flex flex-col sm:flex-row gap-5 sm:items-center">
            @php
                $logo = $store?->logoToko;
                $logoUrl = $logo
                    ? (\Illuminate\Support\Str::startsWith($logo, ['http://', 'https://']) ? $logo : asset('storage/'.$logo))
                    : null;
            @endphp
            <div class="w-20 h-20 rounded-lg bg-gray-100 border border-gray-200 overflow-hidden flex items-center justify-center shrink-0">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Logo {{ $store->nama }}" class="w-full h-full object-cover">
                @else
                    <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l2-5h14l2 5M5 13v7h14v-7M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0"/>
                    </svg>
                @endif
            </div>
            <div class="min-w-0">
                <label for="logo" class="block text-sm font-semibold text-gray-800">Logo toko</label>
                <input id="logo" name="logo" type="file" accept=".jpg,.jpeg,.png,.webp"
                    class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-semibold">
                <p class="text-xs text-gray-400 mt-1">JPG, PNG, atau WebP. Maksimal 4 MB.</p>
                @error('logo')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="p-5 md:p-7 grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="nama" class="block text-sm font-semibold text-gray-700 mb-2">Nama toko</label>
                <input id="nama" name="nama" value="{{ old('nama', $store?->nama) }}" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                @error('nama')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Nomor telepon</label>
                <input id="phone" name="phone" value="{{ old('phone', $store?->phone ?? session('user.phone')) }}" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label for="alamat" class="block text-sm font-semibold text-gray-700 mb-2">Alamat operasional</label>
                <textarea id="alamat" name="alamat" rows="3" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">{{ old('alamat', $store?->alamat) }}</textarea>
                @error('alamat')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label for="deskripsi" class="block text-sm font-semibold text-gray-700 mb-2">Deskripsi toko</label>
                <textarea id="deskripsi" name="deskripsi" rows="5"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">{{ old('deskripsi', $store?->deskripsi) }}</textarea>
                @error('deskripsi')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="px-5 md:px-7 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-4">
            <p class="text-xs text-gray-500">
                Status: <span class="font-semibold capitalize">{{ $store?->tokoStatus ?? 'belum diajukan' }}</span>
            </p>
            <button type="submit" class="px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                Simpan Profil
            </button>
        </div>
    </form>
</div>
@endsection
