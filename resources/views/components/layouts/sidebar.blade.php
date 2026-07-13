@php
    $isSupplier = session('user.role') === 'supplier';
@endphp

@once
    <style>
        .sidebar-shell {
            position: fixed;
            width: var(--sidebar-width);
        }

        @media (min-width: 1024px) {
            .sidebar-shell {
                position: relative;
                transition: width 200ms ease, transform 300ms ease-in-out;
            }

            .sidebar-shell.sidebar-collapsed {
                width: 5rem;
            }

            .sidebar-shell.sidebar-collapsed .sidebar-copy {
                display: none !important;
            }

            .sidebar-shell.sidebar-collapsed .sidebar-logo-wrap,
            .sidebar-shell.sidebar-collapsed .sidebar-menu-link,
            .sidebar-shell.sidebar-collapsed .sidebar-logout-button {
                justify-content: center;
            }

            .sidebar-shell.sidebar-collapsed .sidebar-logo-wrap,
            .sidebar-shell.sidebar-collapsed .sidebar-menu-scroll,
            .sidebar-shell.sidebar-collapsed .sidebar-bottom-area {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            .sidebar-shell.sidebar-collapsed .sidebar-menu-link,
            .sidebar-shell.sidebar-collapsed .sidebar-logout-button {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
        }
    </style>
@endonce

{{-- Sidebar --}}
<aside
    id="sidebar"
    x-data="{
        collapsed: localStorage.getItem('smartfarm.sidebarCollapsed') === '1',
        setCollapsed(value) {
            this.collapsed = value;
            localStorage.setItem('smartfarm.sidebarCollapsed', value ? '1' : '0');
        }
    }"
    :class="collapsed ? 'sidebar-collapsed' : ''"
    class="sidebar-shell top-0 left-0 z-50 h-full bg-white flex flex-col border-r border-[var(--color-gray-200)]
              -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0"
    style="box-shadow: var(--shadow-sm);">
    {{-- Logo --}}
    <div class="sidebar-logo-wrap relative flex items-center gap-3 px-6 py-5 border-b border-[var(--color-gray-100)]">
        <div class="w-10 h-10 bg-[var(--color-primary)] rounded-xl flex items-center justify-center text-sm font-black text-white">
            SF
        </div>
        <div class="sidebar-copy text-lg font-bold text-[var(--color-gray-900)]">Smart<span
                class="text-[var(--color-primary)]">Farm</span></div>
        <button
            type="button"
            class="hidden lg:inline-flex absolute -right-3 top-6 h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-200"
            :aria-label="collapsed ? 'Tampilkan sidebar' : 'Minimize sidebar'"
            :title="collapsed ? 'Tampilkan sidebar' : 'Minimize sidebar'"
            @click="setCollapsed(!collapsed)"
        >
            <svg x-show="!collapsed" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            <svg x-show="collapsed" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </div>

    {{-- Menu Utama --}}
    <div class="sidebar-menu-scroll flex-1 overflow-y-auto px-4 py-4 flex flex-col justify-between">
        @if($isSupplier)
            <div>
                <div class="mb-5">
                    <div class="sidebar-copy text-[11px] font-semibold uppercase tracking-wider text-[var(--color-gray-400)] px-3 mb-2">
                        Panel Supplier
                    </div>
                    <ul class="space-y-1 list-none p-0 m-0">
                        <li>
                            <x-sidebar.menu-item :href="route('supplier.dashboard')" :active="request()->routeIs('supplier.dashboard')" icon="home">
                                Dashboard Toko
                            </x-sidebar.menu-item>
                        </li>
                        <li>
                            <x-sidebar.menu-item :href="route('supplier.products.index')" :active="request()->routeIs('supplier.products.*')" icon="box">
                                Produk
                            </x-sidebar.menu-item>
                        </li>
                        <li>
                            <x-sidebar.menu-item :href="route('supplier.orders.index')" :active="request()->routeIs('supplier.orders.*')" icon="cart">
                                Pesanan
                            </x-sidebar.menu-item>
                        </li>
                        <li>
                            <x-sidebar.menu-item :href="route('supplier.finance')" :active="request()->routeIs('supplier.finance')" icon="wallet">
                                Keuangan
                            </x-sidebar.menu-item>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="sidebar-bottom-area mt-auto pt-4 border-t border-[var(--color-gray-100)]">
                <ul class="space-y-1 list-none p-0 m-0">
                    <li>
                        <x-sidebar.menu-item :href="route('supplier.store.edit')" :active="request()->routeIs('supplier.store.*')" icon="store">
                            Profil Toko
                        </x-sidebar.menu-item>
                    </li>
                </ul>
            </div>
        @else
            <div>
                {{-- Section: Data Operasional --}}
                <div class="mb-5">
                    <div class="sidebar-copy text-[11px] font-semibold uppercase tracking-wider text-[var(--color-gray-400)] px-3 mb-2">
                        Operasional
                    </div>
                    <ul class="space-y-1 list-none p-0 m-0">
                        <li>
                            <x-sidebar.menu-item :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">
                                Dashboard
                            </x-sidebar.menu-item>
                        </li>
                        <li>
                            <x-sidebar.menu-item :href="route('peternakan')" :active="request()->routeIs('peternakan')" icon="livestock">
                                Peternakan
                            </x-sidebar.menu-item>
                        </li>
                        <li>
                            <x-sidebar.menu-item :href="route('perkebunan.index')" :active="request()->routeIs('perkebunan.index')" icon="leaf">
                                Perkebunan
                            </x-sidebar.menu-item>
                        </li>
                        <li>
                            <x-sidebar.menu-item :href="route('inventory')" :active="request()->routeIs('inventory')" icon="database">
                                Inventaris
                            </x-sidebar.menu-item>
                        </li>
                    </ul>
                </div>

                {{-- Section: Monitoring IoT --}}
                <div class="mb-5">
                    <div class="sidebar-copy text-[11px] font-semibold uppercase tracking-wider text-[var(--color-gray-400)] px-3 mb-2">
                        Infrastruktur & Monitoring
                    </div>
                    <ul class="space-y-1 list-none p-0 m-0">
                        <li>
                            <x-sidebar.menu-item :href="route('spk.dashboard')" :active="request()->routeIs('spk.dashboard')" icon="chart">
                                Analisa SPK
                            </x-sidebar.menu-item>
                        </li>
                        <li>
                            <x-sidebar.menu-item :href="route('spk.tasks.index')" :active="request()->routeIs('spk.tasks.*')" icon="clipboard">
                                Penugasan
                            </x-sidebar.menu-item>
                        </li>
                        <li>
                            <x-sidebar.menu-item :href="route('spk.suppliers.index')" :active="request()->routeIs('spk.suppliers.*')" icon="users">
                                Daftar Supplier
                            </x-sidebar.menu-item>
                        </li>
                        <li>
                            <x-sidebar.menu-item :href="route('iot.dashboard')" :active="request()->routeIs('iot.*')" icon="iot">
                                IoT
                            </x-sidebar.menu-item>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Section: Settings --}}
            <div class="sidebar-bottom-area mt-auto pt-4 border-t border-[var(--color-gray-100)]">
                <ul class="space-y-1 list-none p-0 m-0">
                    @if(session('user') && isset(session('user')['role']) && session('user')['role'] === 'pjawab')
                        <li>
                            <x-sidebar.menu-item :href="route('users.index')" :active="request()->routeIs('users.*')" icon="users">
                                Manajemen Karyawan
                            </x-sidebar.menu-item>
                        </li>
                    @endif
                    <li>
                        <x-sidebar.menu-item :href="route('settings.index')" :active="request()->routeIs('settings.*')" icon="settings">
                            Pengaturan
                        </x-sidebar.menu-item>
                    </li>
                </ul>
            </div>
        @endif
    </div>

    {{-- Logout --}}
    <div class="sidebar-bottom-area px-4 pb-4 pt-2">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-logout-button flex items-center gap-3 w-full py-3 px-4 rounded-xl
                           text-[var(--color-danger)] text-sm font-medium
                           bg-[var(--color-danger-light)] bg-opacity-30 border-none cursor-pointer
                           hover:bg-[var(--color-danger)] hover:text-white transition-all duration-200"
                    title="Keluar">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span class="sidebar-copy">Keluar</span>
            </button>
        </form>
    </div>
</aside>
