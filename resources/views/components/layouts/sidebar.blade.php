{{-- Sidebar Overlay (Mobile) --}}
<div id="sidebarOverlay"
    class="fixed inset-0 bg-black/40 z-40 hidden opacity-0 transition-opacity duration-300 lg:hidden"
    onclick="toggleSidebar()">
</div>

{{-- Sidebar --}}
<aside id="sidebar" class="fixed top-0 left-0 z-50 h-full w-[var(--sidebar-width)]
              bg-white flex flex-col border-r border-[var(--color-gray-200)]
              -translate-x-full transition-transform duration-300 ease-in-out
              lg:translate-x-0 lg:static lg:z-auto" style="box-shadow: var(--shadow-sm);">
    {{-- Logo --}}
    <div class="flex items-center gap-3 px-6 py-5 border-b border-[var(--color-gray-100)]">
        <div class="w-10 h-10 bg-[var(--color-primary)] rounded-xl flex items-center justify-center text-xl">🌾
        </div>
        <div class="text-lg font-bold text-[var(--color-gray-900)]">Smart<span
                class="text-[var(--color-primary)]">Farm</span></div>
    </div>

    {{-- Menu Utama --}}
    <div class="flex-1 overflow-y-auto px-4 py-4">
        <div class="mb-4">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-gray-400)] px-3 mb-2">
                Menu Utama</div>
            <ul class="space-y-1 list-none p-0 m-0">
                <li>
                    <x-sidebar.menu-item :href="route('dashboard')" :active="request()->routeIs('dashboard')"
                        icon="home">
                        Dashboard
                    </x-sidebar.menu-item>
                </li>
                <li>
                    <x-sidebar.menu-item :href="route('peternakan')" :active="request()->routeIs('peternakan')"
                        icon="livestock">
                        Peternakan
                    </x-sidebar.menu-item>
                </li>
                <li>
                    <x-sidebar.menu-item :href="route('perkebunan.index')"
                        :active="request()->routeIs('perkebunan.index')" icon="leaf">
                        Perkebunan
                    </x-sidebar.menu-item>
                </li>
                <li>
                    <x-sidebar.menu-item :href="route('inventory')" :active="request()->routeIs('inventory')"
                        icon="database">
                        Inventaris
                    </x-sidebar.menu-item>
                </li>
            </ul>
        </div>

        {{-- Section: Informasi --}}
        <div class="mb-4">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-gray-400)] px-3 mb-2">
                Informasi</div>
            <ul class="space-y-1 list-none p-0 m-0">
                <li>
                    <x-sidebar.menu-item :href="route('data-master.index')"
                        :active="request()->routeIs('data-master.*')" icon="database">
                        Data Master
                    </x-sidebar.menu-item>
                </li>
            </ul>
        </div>

        {{-- IoT Management — Collapsible --}}
        <div class="mb-4" x-data="{ open: {{ request()->routeIs('iot.*') ? 'true' : 'false' }} }">
            <button @click="open = !open"
                class="flex items-center justify-between w-full text-[11px] font-semibold uppercase tracking-wider text-[var(--color-gray-400)] px-3 mb-2 bg-transparent border-none cursor-pointer hover:text-[var(--color-gray-600)] transition-colors duration-200">
                <span>IoT Management</span>
                <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="open && 'rotate-180'" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <ul x-show="open" x-collapse class="space-y-1 list-none p-0 m-0">
                <li>
                    <x-sidebar.menu-item :href="route('iot.dashboard')" :active="request()->routeIs('iot.dashboard')"
                        icon="iot">
                        Dashboard IoT
                    </x-sidebar.menu-item>
                </li>
                <li>
                    <x-sidebar.menu-item :href="route('iot.devices')" :active="request()->routeIs('iot.devices')"
                        icon="cpu">
                        Devices
                    </x-sidebar.menu-item>
                </li>
                <li>
                    <x-sidebar.menu-item :href="route('iot.config')" :active="request()->routeIs('iot.config')"
                        icon="settings">
                        Konfigurasi
                    </x-sidebar.menu-item>
                </li>
                <li>
                    <x-sidebar.menu-item :href="route('iot.monitoring')" :active="request()->routeIs('iot.monitoring')"
                        icon="chart">
                        Monitoring
                    </x-sidebar.menu-item>
                </li>
            </ul>
        </div>

        <div class="mb-4">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-gray-400)] px-3 mb-2">
                Akun</div>
            <ul class="space-y-1 list-none p-0 m-0">
                <li>
                    <x-sidebar.menu-item :href="route('profile')" :active="request()->routeIs('profile')" icon="user">
                        Profil Saya
                    </x-sidebar.menu-item>
                </li>
            </ul>
        </div>
    </div>

    {{-- Logout --}}
    <div class="px-4 py-4 border-t border-[var(--color-gray-100)]">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex items-center gap-3 w-full py-3 px-4 rounded-xl
                           text-[var(--color-danger)] text-sm font-medium
                           bg-transparent border-none cursor-pointer
                           hover:bg-[var(--color-danger-light)] transition-all duration-200">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Keluar
            </button>
        </form>
    </div>
</aside>