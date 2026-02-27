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
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

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

        {{-- Menu Utama --}}
        <x-layouts.sidebar />

        {{-- Content Wrapper --}}
        <div class="flex-1 flex flex-col min-w-0">
            {{-- Top Bar & Notifications --}}
            <x-layouts.topbar />

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