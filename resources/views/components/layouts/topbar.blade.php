<?php
// $notifications = DB::table('notifications')
//     ->where()
//     ->orderBy
//     ->limit(20)
//     ->get
//     ->map(function ($notification) {
//         $data = json_decode($notification->data);
//         return [
//             'id' => $notification->id,
//             'title' => $data['title'] ?? 'Notifikasi',
//             'message' => $data['message'] ?? '',
//             'type' => $data['type'] ?? 'info',
//             'read_at' => $notification->read_at,
//             'created_at' => Carbon::parse($notification->created_at)->diffForHumans(),
//         ];
//     });

$notifications = collect([
    [
        'id' => 1,
        'title' => 'Suhu Tinggi',
        'message' => 'Suhu kandang 3 mencapai 35°C',
        'type' => 'warning',
        'read_at' => '2022-01-01 00:00:00',
        'created_at' => '5 menit lalu',
    ],
    [
        'id' => 2,
        'title' => 'Produksi Telur Stabil',
        'message' => 'HDP meningkat 2% hari ini',
        'type' => 'success',
        'read_at' => "2022-01-01 00:00:01",
        'created_at' => '1 jam lalu',
    ],
]);

$icons = [
    'notification1' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0"/>',
    'notification2' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17H5.143A2.143 2.143 0 013 14.857V11a6 6 0 1112 0v3.857A2.143 2.143 0 0114.857 17zM9 20a3 3 0 006 0" />',
];
?>

<header
    class="bg-white border-b border-[var(--color-gray-200)] px-4 md:px-6 py-3 flex justify-between items-center min-h-[60px] sticky top-0 z-30"
    style="box-shadow: var(--shadow-sm);">
    <div class="flex items-center gap-3">
        {{-- Hamburger (Mobile) --}}
        <button
            class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-[var(--color-gray-100)] transition-colors cursor-pointer bg-transparent border-none"
            onclick="toggleSidebar()" aria-label="Toggle menu">
            <svg class="w-6 h-6 text-[var(--color-gray-700)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        {{-- Breadcrumb --}}
        <nav class="flex items-center gap-2 text-sm">
            <a href="{{ route('dashboard') }}"
                class="text-[var(--color-gray-400)] hover:text-[var(--color-primary)] no-underline transition-colors">Smart
                Farm</a>
            <span class="text-[var(--color-gray-300)]">›</span>
            <span class="text-[var(--color-gray-900)] font-medium">@yield('breadcrumb', 'Dashboard')</span>
        </nav>
    </div>
    <div class="flex items-center gap-3">
        {{-- User Info --}}
        <div x-data="{ showProfileMenu: false }" class="relative flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <div class="text-sm font-semibold text-[var(--color-gray-900)]">
                    {{ $authUser['name'] ?? 'User' }}
                </div>
                <div class="text-xs text-[var(--color-gray-400)]">{{ ucfirst($authUser['role'] ?? '-') }}
                </div>
            </div>
            
            <button @click="showProfileMenu = !showProfileMenu" class="focus:outline-none rounded-full transition-transform hover:scale-105 active:scale-95">
                @if (!empty($authUser['avatar']))
                    <img src="{{ $authUser['avatar'] }}" alt="Avatar"
                        class="w-9 h-9 rounded-full object-cover ring-2 ring-[var(--color-primary-light)]">
                @else
                    <div class="w-9 h-9 rounded-full bg-[var(--color-primary-light)] flex items-center justify-center
                                                            font-bold text-sm text-[var(--color-primary-dark)]">
                        {{ strtoupper(substr($authUser['name'] ?? 'U', 0, 1)) }}
                    </div>
                @endif
            </button>

            <!-- Profile Dropdown -->
            <div x-show="showProfileMenu" @click.away="showProfileMenu = false"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-1"
                class="absolute right-0 top-12 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 z-50 overflow-hidden"
                style="display: none;">
                <div class="p-2">
                    <a href="{{ route('profile') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Profil Saya
                    </a>
                </div>
            </div>
        </div>

        {{-- Notification --}}
        <div x-data="{ showNotifications: false }" class="relative">
            <button @click="showNotifications = !showNotifications"
                class="relative p-2 rounded-full hover:bg-gray-100 transition">
                <!-- <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    class="w-6 h-6">
                    {!! $icons['notification2'] !!}
                </svg> -->
                <img src="/assets/icons/notification.svg" class="w-5 h-5">
                @if($notifications->where('read_at', null)->count() > 0)
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></span>
                @endif
            </button>

            <!-- Dropdown Menu -->
            <div x-show="showNotifications" @click.away="showNotifications = false"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-1"
                class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-lg border border-gray-100 z-50 overflow-hidden"
                style="display: none;">
                <div class="px-4 py-3 border-b flex items-center justify-between bg-gray-50">
                    <h3 class="font-semibold text-gray-800">Notifikasi</h3>
                    <span class="text-xs text-gray-500">{{ $notifications->count() }} Terkini</span>
                </div>

                <div class="max-h-[60vh] overflow-y-auto">
                    @forelse($notifications as $notif)
                        <div
                            class="p-4 border-b hover:bg-gray-50 transition-colors {{ !$notif['read_at'] ? 'bg-blue-50/50' : '' }}">
                            <div class="flex gap-3">
                                <div class="mt-1 shrink-0">
                                    @if($notif['type'] == 'danger')
                                        <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </div>
                                    @elseif($notif['type'] == 'warning')
                                        <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center">
                                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                    @else
                                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <p
                                        class="text-sm font-semibold text-gray-900 {{ !$notif['read_at'] ? 'font-bold' : '' }}">
                                        {{ $notif['title'] }}
                                    </p>
                                    <p class="text-xs text-gray-600 mt-0.5">{{ $notif['message'] }}</p>
                                    <p class="text-[10px] text-gray-400 mt-1">{{ $notif['created_at'] }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-500">
                            <p class="text-sm">Belum ada notifikasi</p>
                        </div>
                    @endforelse
                </div>

                <div class="p-2 border-t bg-gray-50 text-center">
                    <a href="#" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">Lihat
                        Semua History</a>
                </div>
            </div>
        </div>
    </div>
</header>