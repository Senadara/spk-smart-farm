<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Smart Farm SPK</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full font-primary text-[var(--color-gray-900)]">
    <div class="flex h-full">
        {{-- Sidebar Overlay (Mobile) --}}
        <div id="sidebarOverlay"
             class="fixed inset-0 bg-black/40 z-40 hidden opacity-0 transition-opacity duration-300 lg:hidden"
             onclick="toggleSidebar()">
        </div>

        {{-- Sidebar --}}
        <aside id="sidebar"
               class="fixed top-0 left-0 z-50 h-full w-[var(--sidebar-width)]
                      bg-white flex flex-col border-r border-[var(--color-gray-200)]
                      -translate-x-full transition-transform duration-300 ease-in-out
                      lg:translate-x-0 lg:static lg:z-auto"
               style="box-shadow: var(--shadow-sm);">
            {{-- Logo --}}
            <div class="flex items-center gap-3 px-6 py-5 border-b border-[var(--color-gray-100)]">
                <div class="w-10 h-10 bg-[var(--color-primary)] rounded-xl flex items-center justify-center text-xl">🌾</div>
                <div class="text-lg font-bold text-[var(--color-gray-900)]">Smart<span class="text-[var(--color-primary)]">Farm</span></div>
            </div>

            {{-- Menu Utama --}}
            <div class="flex-1 overflow-y-auto px-4 py-4">
                <div class="mb-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-gray-400)] px-3 mb-2">Menu Utama</div>
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
                    </ul>
                </div>

                <div class="mb-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-gray-400)] px-3 mb-2">Akun</div>
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
                    <button type="submit"
                            class="flex items-center gap-3 w-full py-3 px-4 rounded-xl
                                   text-[var(--color-danger)] text-sm font-medium
                                   bg-transparent border-none cursor-pointer
                                   hover:bg-[var(--color-danger-light)] transition-all duration-200">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Keluar
                    </button>
                </form>
            </div>
        </aside>

        {{-- Content Wrapper --}}
        <div class="flex-1 flex flex-col min-w-0">
            {{-- Top Bar --}}
            <header class="bg-white border-b border-[var(--color-gray-200)] px-4 md:px-6 py-3 flex justify-between items-center min-h-[60px] sticky top-0 z-30"
                    style="box-shadow: var(--shadow-sm);">
                <div class="flex items-center gap-3">
                    {{-- Hamburger (Mobile) --}}
                    <button class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-[var(--color-gray-100)] transition-colors cursor-pointer bg-transparent border-none"
                            onclick="toggleSidebar()" aria-label="Toggle menu">
                        <svg class="w-6 h-6 text-[var(--color-gray-700)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    {{-- Breadcrumb --}}
                    <nav class="flex items-center gap-2 text-sm">
                        <a href="{{ route('dashboard') }}" class="text-[var(--color-gray-400)] hover:text-[var(--color-primary)] no-underline transition-colors">Smart Farm</a>
                        <span class="text-[var(--color-gray-300)]">›</span>
                        <span class="text-[var(--color-gray-900)] font-medium">@yield('breadcrumb', 'Dashboard')</span>
                    </nav>
                </div>

                {{-- User Info --}}
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <div class="text-sm font-semibold text-[var(--color-gray-900)]">{{ $authUser['name'] ?? 'User' }}</div>
                        <div class="text-xs text-[var(--color-gray-400)]">{{ ucfirst($authUser['role'] ?? '-') }}</div>
                    </div>
                    @if (!empty($authUser['avatar']))
                        <img src="{{ $authUser['avatar'] }}" alt="Avatar"
                             class="w-9 h-9 rounded-full object-cover ring-2 ring-[var(--color-primary-light)]">
                    @else
                        <div class="w-9 h-9 rounded-full bg-[var(--color-primary-light)] flex items-center justify-center
                                    font-bold text-sm text-[var(--color-primary-dark)]">
                            {{ strtoupper(substr($authUser['name'] ?? 'U', 0, 1)) }}
                        </div>
                    @endif
                </div>
            </header>

            {{-- Main Content --}}
            <main class="flex-1 p-4 md:p-6 bg-[var(--color-gray-50)] overflow-y-auto">
                @yield('content')
            </main>
        </div>
    </div>

    {{-- Toast Notifications --}}
    @include('components.toast')

    {{-- Sidebar Toggle Script --}}
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const isOpen = !sidebar.classList.contains('-translate-x-full');

            if (isOpen) {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                overlay.classList.remove('opacity-100');
            } else {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.add('opacity-100'), 10);
            }
        }
    </script>

    @stack('scripts')
</body>
</html>
