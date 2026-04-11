@extends('layouts.app')

@section('title', 'Katalog Rekomendasi Supplier')
@section('breadcrumb', 'Supplier')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    {{-- HEADER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-6 h-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Katalog Supplier Peternakan
                </h1>
                <p class="text-sm text-gray-500 mt-1">Temukan supplier pakan, obat, dan alat peternakan terpercaya di sekitar Anda.</p>
            </div>

            <form action="{{ route('spk.suppliers.index') }}" method="GET" class="flex items-center gap-3">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama toko/kota..." class="w-64 pl-9 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-sm">
                </div>
                <button type="submit" class="hidden"></button>
            </form>
        </div>
        
        {{-- FILTERS --}}
        <div class="mt-6 flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest mr-2">Cepat: </span>
            
            <a href="{{ route('spk.suppliers.index') }}" class="px-4 py-1.5 rounded-full text-sm font-medium transition {{ $category === 'all' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/30' : 'bg-gray-50 text-gray-600 hover:bg-emerald-50 hover:text-emerald-600 border border-gray-100' }}">Semua</a>
            
            <a href="{{ route('spk.suppliers.index', ['category' => 'pakan']) }}" class="px-4 py-1.5 rounded-full text-sm font-medium transition {{ $category === 'pakan' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/30' : 'bg-gray-50 text-gray-600 hover:bg-emerald-50 hover:text-emerald-600 border border-gray-100' }}">🌽 Pakan Pokok</a>
            
            <a href="{{ route('spk.suppliers.index', ['category' => 'obat']) }}" class="px-4 py-1.5 rounded-full text-sm font-medium transition {{ $category === 'obat' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/30' : 'bg-gray-50 text-gray-600 hover:bg-emerald-50 hover:text-emerald-600 border border-gray-100' }}">💊 Obat & Vaksin</a>
            
            <a href="{{ route('spk.suppliers.index', ['category' => 'alat']) }}" class="px-4 py-1.5 rounded-full text-sm font-medium transition {{ $category === 'alat' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/30' : 'bg-gray-50 text-gray-600 hover:bg-emerald-50 hover:text-emerald-600 border border-gray-100' }}">⚙️ Peralatan Kandang</a>

            <div class="flex-grow"></div>
            
            <a href="{{ route('spk.suppliers.products') }}" class="px-4 py-1.5 rounded-lg text-sm font-bold bg-purple-50 text-purple-600 hover:bg-purple-100 transition border border-purple-100 flex items-center gap-1.5">
               <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
               Mode Banding Barang
            </a>
        </div>
    </div>

    {{-- SUPPLIER GRID --}}
    @if(empty($suppliers))
    <div class="bg-white rounded-xl p-12 text-center shadow-sm border border-gray-50">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        <h3 class="text-lg font-bold text-gray-800">Tidak ada supplier ditemukan</h3>
        <p class="text-sm text-gray-500 mt-1">Coba gunakan kata kunci pencarian lain atau pilih kategori Semua.</p>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($suppliers as $supplier)
        <a href="{{ route('spk.suppliers.show', $supplier['id']) }}" class="group block bg-white rounded-2xl shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border border-emerald-50 overflow-hidden">
            
            <div class="p-5">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <img src="{{ $supplier['logo'] }}" alt="Logo {{ $supplier['name'] }}" class="w-12 h-12 rounded-xl object-cover ring-2 ring-emerald-50 group-hover:ring-emerald-200 transition">
                        <div>
                            <h3 class="font-bold text-gray-900 group-hover:text-emerald-600 transition">{{ $supplier['name'] }}</h3>
                            <p class="text-[11px] text-emerald-600 font-semibold mt-0.5 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                                Terverifikasi
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 break-words">
                    <p class="text-sm text-gray-500 line-clamp-2 leading-relaxed">
                        {{ $supplier['description'] }}
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @foreach($supplier['categories'] as $cat)
                        <span class="px-2.5 py-1 bg-gray-50 border border-gray-100 text-gray-600 text-[10px] font-semibold rounded-lg uppercase tracking-wider">
                            {{ $cat }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="bg-gray-50/50 px-5 py-3.5 border-t border-emerald-50 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex items-center text-amber-400">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <span class="ml-1 text-sm font-bold text-gray-700">{{ $supplier['rating'] }}</span>
                        <span class="ml-1 text-xs text-gray-400">({{ $supplier['reviews'] }})</span>
                    </div>
                </div>
                
                <div class="flex items-center text-xs text-gray-500 font-medium">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $supplier['distance'] }}
                </div>
            </div>

            {{-- Skor Kecocokan SPK Indicator --}}
            <div class="absolute top-4 right-4 bg-white/90 backdrop-blur border border-emerald-100 shadow-sm px-2.5 py-1.5 rounded-xl flex items-center justify-center text-center">
                <span class="w-2 h-2 rounded-full {{ $supplier['score'] >= 90 ? 'bg-emerald-500' : 'bg-amber-500' }} animate-pulse mr-1.5"></span>
                <span class="text-xs font-black {{ $supplier['score'] >= 90 ? 'text-emerald-700' : 'text-amber-700' }}">{{ $supplier['score'] }}% Match</span>
            </div>

        </a>
        @endforeach
    </div>
    @endif
</div>
@endsection
