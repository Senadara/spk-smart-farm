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
    <div class="flex-1 overflow-y-auto px-4 py-4 flex flex-col justify-between">
        <div>
            {{-- Section: Data Operasional --}}
            <div class="mb-5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-gray-400)] px-3 mb-2">
                    Operasional</div>
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

            {{-- Section: Monitoring IoT --}}
            <div class="mb-5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-gray-400)] px-3 mb-2">
                    Infrastruktur & Monitoring</div>
                <ul class="space-y-1 list-none p-0 m-0">
                    <li>
                        <x-sidebar.menu-item :href="route('spk.dashboard')" :active="request()->routeIs('spk.*')"
                            icon="chart">
                            Analisa SPK
                        </x-sidebar.menu-item>
                    </li>
                    <li>
                        <x-sidebar.menu-item :href="route('iot.dashboard')" :active="request()->routeIs('iot.dashboard')"
                            icon="iot">
                            Dashboard IoT
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
        </div>

        {{-- Section: Settings (Grounded at bottom of flex-1) --}}
        <div class="mt-auto pt-4 border-t border-[var(--color-gray-100)]">
            <ul class="space-y-1 list-none p-0 m-0">
                <li>
                    <x-sidebar.menu-item :href="route('settings.index')" :active="request()->routeIs('settings.*')"
                        icon="settings">
                        Pengaturan
                    </x-sidebar.menu-item>
                </li>
            </ul>
        </div>
    </div>

    {{-- Logout --}}
    <div class="px-4 pb-4 pt-2">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex items-center gap-3 w-full py-3 px-4 rounded-xl
                           text-[var(--color-danger)] text-sm font-medium
                           bg-[var(--color-danger-light)] bg-opacity-30 border-none cursor-pointer
                           hover:bg-[var(--color-danger)] hover:text-white transition-all duration-200">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Keluar
            </button>
        </form>
    </div>
</aside>