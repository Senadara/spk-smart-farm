@extends('layouts.app')

@section('title', 'Perbandingan Produk')
@section('breadcrumb', 'Supplier > Komparasi Barang')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Perbandingan Produk Supplier</h1>
            <p class="text-sm text-gray-500 mt-1">Cari harga termurah dan stok terbanyak dari berbagai supplier sekaligus.</p>
        </div>
        <a href="{{ route('spk.suppliers.index') }}" class="text-sm font-semibold text-gray-500 bg-white border border-gray-200 px-4 py-2 rounded-xl hover:bg-gray-50 transition">
            ← Kembali ke Katalog
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
        
        {{-- LEFT SIDEBAR: PRODUCT LIST --}}
        <div class="md:col-span-4 lg:col-span-3">
            <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 overflow-hidden sticky top-6">
                <div class="p-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Katalog Kebutuhan Pokok</h3>
                </div>
                
                {{-- Search Bar Barang --}}
                <div class="p-3 border-b border-gray-100">
                    <form action="{{ route('spk.suppliers.products') }}" method="GET">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Cari barang..." class="w-full pl-8 pr-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:border-emerald-500 focus:bg-white text-xs">
                        </div>
                    </form>
                </div>

                <div class="divide-y divide-gray-50 max-h-[600px] overflow-y-auto">
                    @forelse($products as $prod)
                        <a href="{{ route('spk.suppliers.products', ['product_id' => $prod['id'], 'search' => $search ?? '']) }}" class="flex items-center gap-3 p-4 hover:bg-emerald-50 transition {{ ($activeProduct['id'] ?? '') === $prod['id'] ? 'bg-emerald-50/70 border-l-4 border-emerald-500' : 'border-l-4 border-transparent' }}">
                            <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center text-xl shadow-sm border border-gray-100">
                                {{ $prod['icon'] }}
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 line-clamp-1">{{ $prod['name'] }}</h4>
                                <span class="text-[10px] text-gray-500">{{ $prod['category'] }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="p-6 text-center text-gray-500 text-sm">
                            Tidak ada produk yang cocok dengan pencarian Anda.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- RIGHT SIDEBAR: COMPARISON TABLE --}}
        <div class="md:col-span-8 lg:col-span-9">
            @if($activeProduct)
                <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 p-6 mb-6">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-xl bg-purple-50 text-3xl flex items-center justify-center border border-purple-100">
                                {{ $activeProduct['icon'] }}
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">{{ $activeProduct['name'] }}</h2>
                                <p class="text-sm text-gray-500">Harga dan ketersediaan barang di berbagai supplier.</p>
                            </div>
                        </div>

                        {{-- Filter Tabel --}}
                        <div class="bg-gray-50 rounded-lg border border-gray-100 p-2 shrink-0">
                            <form id="filterTableForm" action="{{ route('spk.suppliers.products') }}" method="GET" class="flex flex-wrap items-center gap-2">
                                <input type="hidden" name="product_id" value="{{ $activeProduct['id'] }}">
                                @if(!empty($search))
                                    <input type="hidden" name="search" value="{{ $search }}">
                                @endif
                                
                                <select name="sort" onchange="document.getElementById('filterTableForm').submit()" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:border-emerald-500 font-medium text-gray-600">
                                    <option value="default" {{ $filterSort === 'default' ? 'selected' : '' }}>Urutan Standar</option>
                                    <option value="cheapest" {{ $filterSort === 'cheapest' ? 'selected' : '' }}>⬇ Harga Termurah</option>
                                    <option value="closest" {{ $filterSort === 'closest' ? 'selected' : '' }}>📍 Jarak Terdekat</option>
                                </select>

                                <select name="stock" onchange="document.getElementById('filterTableForm').submit()" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:border-emerald-500 font-medium text-gray-600">
                                    <option value="all" {{ $filterStock === 'all' ? 'selected' : '' }}>Semua Ketersediaan</option>
                                    <option value="instock" {{ $filterStock === 'instock' ? 'selected' : '' }}>Hanya Tersedia (>0)</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    @if(empty($comparison))
                        <div class="text-center py-12 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-600">Belum ada supplier yang menyediakan produk ini.</h3>
                        </div>
                    @else
                        <div class="overflow-hidden border border-gray-200 rounded-xl">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama Supplier</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Harga Satuan</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Stok</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Jarak / Kirim</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @php 
                                        $cheapest = collect($comparison)->min('price'); 
                                        $closest = collect($comparison)->min('distance');
                                    @endphp
                                    
                                    @foreach($comparison as $c)
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 font-bold">
                                                {{ $c['supplierName'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-bold">
                                                Rp {{ number_format($c['price'], 0, ',', '.') }}
                                                @if($c['price'] == $cheapest)
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                        Termurah
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded-lg text-xs font-semibold">{{ number_format($c['stock']) }} unit</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap flex flex-col justify-center">
                                                <div class="text-sm font-semibold text-gray-700 flex items-center gap-1">
                                                    {{ $c['distance'] }} km 
                                                    @if($c['distance'] == $closest)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Terdekat</span>
                                                    @endif
                                                </div>
                                                <span class="text-[10px] text-gray-400">{{ $c['delivery'] }}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="{{ route('spk.suppliers.show', $c['supplierId']) }}" class="text-emerald-600 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-lg transition font-semibold">
                                                    Buka Toko
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
