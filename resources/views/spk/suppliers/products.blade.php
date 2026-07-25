@extends('layouts.app')

@section('title', 'Cari Barang Supplier')
@section('breadcrumb', 'Supplier > Cari Barang')

@section('content')
<div id="supplier-products-page" class="mx-auto max-w-7xl space-y-5" data-csrf="{{ csrf_token() }}" data-remove-url-template="{{ route('spk.suppliers.cart.remove', ['product' => '__PRODUCT_ID__']) }}">
    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <section class="rounded-lg border border-gray-200 bg-white p-5 md:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Belanja Supplier</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Cari Barang</h1>
                <p class="mt-1 text-sm text-gray-500">Cari barang berdasarkan kategori, bandingkan toko, lalu masukkan ke keranjang.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('spk.suppliers.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Cari Toko</a>
                <a href="{{ route('spk.suppliers.orders.index') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" style="text-decoration:none;">Pesanan Saya</a>
            </div>
        </div>
    </section>

    <x-page-hint title="Cara pesan barang" tone="sky" :open="false">
        Pilih kategori atau cari nama barang. Barang yang dipilih masuk ke keranjang, lalu checkout akan membuat draft pesanan per toko supplier. Pembayaran tetap dikonfirmasi langsung dengan supplier.
    </x-page-hint>

    <section class="rounded-lg border border-gray-200 bg-white p-4 md:p-5">
        <form action="{{ route('spk.suppliers.products') }}" method="GET" class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[240px] flex-1">
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari pakan, vitamin, vaksin, alat..."
                    class="h-11 w-full rounded-lg border border-gray-300 pl-9 pr-3 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
            </div>
            <select name="category" class="h-11 min-w-[170px] flex-1 rounded-lg border border-gray-300 bg-white px-3 text-sm sm:flex-none">
                <option value="all">Semua kategori</option>
                @foreach($categoryOptions as $option)
                    <option value="{{ $option }}" @selected($category === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <select name="sort" class="h-11 min-w-[140px] flex-1 rounded-lg border border-gray-300 bg-white px-3 text-sm sm:flex-none">
                <option value="recommended" @selected($filterSort === 'recommended')>Rekomendasi</option>
                <option value="cheapest" @selected($filterSort === 'cheapest')>Termurah</option>
                <option value="closest" @selected($filterSort === 'closest')>Terdekat</option>
            </select>
            <button class="h-11 flex-1 rounded-lg bg-gray-900 px-5 text-sm font-bold text-white hover:bg-gray-800 sm:flex-none">Cari</button>
        </form>

        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('spk.suppliers.products') }}" class="rounded-full px-4 py-2 text-sm font-semibold {{ $category === 'all' ? 'bg-emerald-600 text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50' }}" style="text-decoration:none;">Semua</a>
            @foreach($categoryOptions->take(8) as $option)
                <a href="{{ route('spk.suppliers.products', ['category' => $option, 'search' => $search, 'sort' => $filterSort]) }}"
                    class="rounded-full px-4 py-2 text-sm font-semibold {{ $category === $option ? 'bg-emerald-600 text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50' }}"
                    style="text-decoration:none;">
                    {{ $option }}
                </a>
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_340px]">
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($products as $product)
                <article class="flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="h-40 bg-gray-100">
                        @if($product['image'])
                            <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-2xl font-black text-gray-300">{{ $product['icon'] }}</div>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-emerald-700">{{ $product['category'] }}</p>
                                <h2 class="mt-1 line-clamp-2 font-bold text-gray-900">{{ $product['name'] }}</h2>
                            </div>
                            <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-700">{{ $product['score'] }}</span>
                        </div>

                        <p class="mt-2 line-clamp-2 text-xs text-gray-500">{{ $product['description'] ?: 'Deskripsi barang belum tersedia.' }}</p>
                        <p class="mt-4 text-lg font-black text-gray-900">Rp {{ number_format($product['price'], 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-500">Stok {{ number_format($product['stock']) }} {{ $product['unit'] }}</p>

                        <div class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-500">
                            <p class="font-bold text-gray-900">{{ $product['store_name'] }}</p>
                            <p class="mt-1 line-clamp-1">{{ $product['store_location'] }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="rounded-full bg-gray-100 px-2 py-1">{{ $product['distance'] }}</span>
                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-emerald-700">{{ $product['delivery'] }}</span>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('spk.suppliers.cart.add') }}" class="mt-4 flex gap-2" data-cart-form>
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product['id'] }}">
                            <label class="sr-only" for="qty-{{ $product['id'] }}">Jumlah</label>
                            <input id="qty-{{ $product['id'] }}" name="quantity" type="number" min="1" max="{{ $product['stock'] }}" value="1"
                                class="w-20 rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <button class="flex-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:bg-emerald-300" data-cart-button>Tambah</button>
                        </form>

                        @if($product['supplier_id'])
                            <a href="{{ route('spk.suppliers.show', $product['supplier_id']) }}" class="mt-2 rounded-lg border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Buka Toko</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-gray-200 bg-white p-10 text-center md:col-span-2 xl:col-span-3">
                    <p class="font-bold text-gray-900">Barang tidak ditemukan</p>
                    <p class="mt-1 text-sm text-gray-500">Coba kata kunci lain atau pilih kategori Semua.</p>
                </div>
            @endforelse
        </section>

        <aside class="h-fit rounded-lg border border-gray-200 bg-white p-5 lg:sticky lg:top-20">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-gray-900">Keranjang</h2>
                    <p id="cart-summary-line" class="text-xs text-gray-500">{{ $cart['total_quantity'] }} barang dari {{ $cart['store_count'] }} toko</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Draft</span>
            </div>
            <div id="cart-feedback" class="mt-4 hidden rounded-lg border px-3 py-2 text-xs font-semibold"></div>

            <div id="cart-items" class="mt-4 space-y-3">
                @forelse($cart['items'] as $item)
                    <div class="rounded-lg border border-gray-100 px-3 py-2">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ $item['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $item['store'] }} - {{ $item['quantity'] }} {{ $item['unit'] }}</p>
                                <p class="mt-1 text-sm font-semibold text-gray-900">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</p>
                            </div>
                            <form method="POST" action="{{ route('spk.suppliers.cart.remove', $item['id']) }}">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs font-semibold text-red-600" data-remove-cart-item="{{ $item['id'] }}">Hapus</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                        Keranjang masih kosong.
                    </div>
                @endforelse
            </div>

            <div class="mt-4 border-t border-gray-100 pt-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Subtotal</span>
                    <span id="cart-subtotal" class="font-black text-gray-900">Rp {{ number_format($cart['subtotal'], 0, ',', '.') }}</span>
                </div>
                <form method="POST" action="{{ route('spk.suppliers.cart.checkout') }}" class="mt-4">
                    @csrf
                    <button id="cart-checkout-button" class="w-full rounded-lg bg-gray-900 px-4 py-3 text-sm font-bold text-white hover:bg-gray-800 disabled:bg-gray-300" @disabled($cart['total_quantity'] <= 0)>
                        Buat Pesanan
                    </button>
                </form>
                <p class="mt-2 text-xs text-gray-500">Pesanan dibuat per toko supplier. Pembayaran dikonfirmasi di luar sistem.</p>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('supplier-products-page');
    if (!page) return;

    const csrf = page.dataset.csrf;
    const removeUrlTemplate = page.dataset.removeUrlTemplate;
    const summaryLine = document.getElementById('cart-summary-line');
    const feedback = document.getElementById('cart-feedback');
    const cartItems = document.getElementById('cart-items');
    const cartSubtotal = document.getElementById('cart-subtotal');
    const checkoutButton = document.getElementById('cart-checkout-button');
    const money = new Intl.NumberFormat('id-ID');

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const formatMoney = (value) => `Rp ${money.format(Number(value || 0))}`;

    const showFeedback = (message, type = 'success') => {
        feedback.textContent = message;
        feedback.className = `mt-4 rounded-lg border px-3 py-2 text-xs font-semibold ${
            type === 'error'
                ? 'border-red-200 bg-red-50 text-red-700'
                : 'border-emerald-200 bg-emerald-50 text-emerald-700'
        }`;
        window.clearTimeout(showFeedback.timer);
        showFeedback.timer = window.setTimeout(() => feedback.classList.add('hidden'), 2600);
    };

    const renderCart = (cart) => {
        summaryLine.textContent = `${cart.total_quantity} barang dari ${cart.store_count} toko`;
        cartSubtotal.textContent = formatMoney(cart.subtotal);
        checkoutButton.disabled = Number(cart.total_quantity || 0) <= 0;

        if (!cart.items.length) {
            cartItems.innerHTML = '<div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">Keranjang masih kosong.</div>';
            return;
        }

        cartItems.innerHTML = cart.items.map((item) => `
            <div class="rounded-lg border border-gray-100 px-3 py-2">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-gray-900">${escapeHtml(item.name)}</p>
                        <p class="text-xs text-gray-500">${escapeHtml(item.store)} - ${escapeHtml(item.quantity)} ${escapeHtml(item.unit)}</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900">${formatMoney(item.subtotal)}</p>
                    </div>
                    <button type="button" class="text-xs font-semibold text-red-600" data-remove-cart-item="${escapeHtml(item.id)}">Hapus</button>
                </div>
            </div>
        `).join('');
    };

    document.querySelectorAll('[data-cart-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('[data-cart-button]');
            button.disabled = true;
            button.textContent = 'Menambah...';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Barang gagal ditambahkan.');
                renderCart(data.cart);
                showFeedback(data.message || 'Barang ditambahkan ke keranjang.');
            } catch (error) {
                showFeedback(error.message || 'Barang gagal ditambahkan.', 'error');
            } finally {
                button.disabled = false;
                button.textContent = 'Tambah';
            }
        });
    });

    cartItems.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-remove-cart-item]');
        if (!button) return;
        event.preventDefault();
        button.disabled = true;

        try {
            const response = await fetch(removeUrlTemplate.replace('__PRODUCT_ID__', encodeURIComponent(button.dataset.removeCartItem)), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Barang gagal dihapus.');
            renderCart(data.cart);
            showFeedback(data.message || 'Barang dihapus dari keranjang.');
        } catch (error) {
            button.disabled = false;
            showFeedback(error.message || 'Barang gagal dihapus.', 'error');
        }
    });
});
</script>
@endpush
