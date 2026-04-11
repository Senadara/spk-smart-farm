@extends('layouts.app')

@section('title', 'Detail Supplier')
@section('breadcrumb', 'Supplier > Detail')

@section('content')
<div class="max-w-7xl mx-auto pb-10">
    <div class="mb-6">
        <a href="{{ route('spk.suppliers.index') }}" class="text-sm font-semibold text-gray-500 bg-white border border-gray-200 px-4 py-2 rounded-xl hover:bg-gray-50 transition">
            ← Kembali ke Katalog
        </a>
    </div>

    {{-- HEADER PROFILE --}}
    <div class="bg-white rounded-3xl shadow-sm border border-emerald-50 overflow-hidden mb-8">
        {{-- Banner Pseudo --}}
        <div class="h-32 bg-gradient-to-r from-emerald-500 to-teal-400 w-full relative">
            <div class="absolute inset-0 opacity-20" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
        </div>

        <div class="px-8 pb-8 pt-0 relative">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                
                {{-- Profile Info --}}
                <div class="flex items-end -mt-12 gap-5 relative z-10">
                    <div class="w-32 h-32 bg-white p-2 rounded-2xl shadow-md border border-gray-100 shrink-0">
                        <img src="{{ $supplier['logo'] }}" alt="Logo" class="w-full h-full rounded-xl object-cover">
                    </div>
                    <div class="pb-2">
                        <div class="flex items-center gap-2 mb-1">
                            <h1 class="text-3xl font-black text-gray-900">{{ $supplier['name'] }}</h1>
                            <span class="bg-emerald-100 text-emerald-700 p-1 rounded-full" title="Terverifikasi SPK">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 font-medium flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ $supplier['location'] }} ({{ $supplier['distance'] }})
                        </p>
                    </div>
                </div>

                {{-- Call to Actions --}}
                <div class="flex flex-wrap gap-3 pb-2 w-full md:w-auto">
                    {{-- WA Button --}}
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $supplier['phone']) }}" target="_blank" class="flex-1 md:flex-none flex items-center justify-center gap-2 bg-[#25D366] hover:bg-[#1DA851] text-white px-6 py-3 rounded-xl font-bold shadow-sm transition">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        Hubungi Penjual
                    </a>
                    
                    {{-- Maps Button --}}
                    <button class="flex-1 md:flex-none flex items-center justify-center gap-2 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 px-6 py-3 rounded-xl font-bold shadow-sm transition">
                        <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        Google Maps
                    </button>
                </div>
            </div>

            {{-- Stat Row --}}
            <div class="mt-6 border-t border-gray-100 pt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-gray-50 rounded-xl p-4 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] uppercase font-bold text-gray-500 mb-1">Algoritma (SPK)</p>
                        <p class="text-xl font-black text-emerald-600">{{ $supplier['score'] }}%</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-xl p-4 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] uppercase font-bold text-gray-500 mb-1">Rating Web</p>
                        <p class="text-xl font-black text-amber-500">{{ $supplier['rating'] }} <span class="text-xs text-gray-400 font-medium">/ 5.0</span></p>
                    </div>
                    <div class="text-amber-400 flex flex-col items-center">
                        <svg class="w-6 h-6 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <span class="text-[9px] text-gray-500 font-bold mt-1">{{ $supplier['reviews'] }} Ulasan</span>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-xl p-4 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] uppercase font-bold text-gray-500 mb-1">Level Harga</p>
                        <p class="text-xl font-black text-gray-800">{{ $supplier['price_tier'] }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-xl p-4 h-full relative overflow-hidden">
                    <p class="text-[10px] uppercase font-bold text-gray-500 mb-1 relative z-10">Tentang Usaha</p>
                    <p class="text-xs text-gray-600 line-clamp-3 leading-relaxed relative z-10">{{ $supplier['description'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- KETERSEDIAAN BARANG --}}
    <div class="bg-white rounded-3xl shadow-sm border border-emerald-50 p-8">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Daftar Barang Tersedia</h2>
                <p class="text-sm text-gray-500 mt-1">Produk peternakan yang ditawarkan oleh supplier (harga dapat berubah).</p>
            </div>
        </div>

        @if(empty($inventories))
            <div class="py-10 text-center">
                <p class="text-gray-500 font-medium">Belum ada daftar barang yang dimasukkan.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($inventories as $inv)
                <div class="group border border-gray-100 hover:border-emerald-200 bg-white hover:shadow-lg rounded-2xl p-5 transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between mb-3">
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 text-[10px] font-bold uppercase tracking-wider rounded">
                                {{ $inv['type'] }}
                            </span>
                            <span class="flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded {{ str_contains(strtolower($inv['stock']), 'banyak') ? 'bg-emerald-100 text-emerald-700' : (str_contains(strtolower($inv['stock']), 'kosong') ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ str_contains(strtolower($inv['stock']), 'banyak') ? 'bg-emerald-500' : (str_contains(strtolower($inv['stock']), 'kosong') ? 'bg-red-500' : 'bg-blue-500') }}"></span>
                                {{ $inv['stock'] }}
                            </span>
                        </div>
                        <h3 class="text-base font-bold text-gray-900 leading-snug mb-2 group-hover:text-emerald-700 transition">{{ $inv['name'] }}</h3>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-50 flex items-end justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 block mb-0.5">Estimasi Harga</span>
                            <span class="text-lg font-black text-gray-900">Rp {{ number_format($inv['price'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
