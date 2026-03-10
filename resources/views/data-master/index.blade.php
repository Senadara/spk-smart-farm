@extends('layouts.app')

@section('title', 'Data Master Operasional')

@section('content')
<div x-data="dataMasterPage()" class="space-y-6">

    {{-- Page Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Data Master Operasional</h1>
        <p class="mt-1 text-sm text-gray-500">
            Informasi pengguna dan blok kebun yang terdaftar di sistem RFC.
            Data bersifat <span class="italic">read-only</span> dan dikelola melalui aplikasi Mobile RFC.
        </p>
    </div>

    {{-- Info Banner (read-only notice) --}}
    <div class="flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl">
        <svg class="w-5 h-5 text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-amber-800">
            Data pada halaman ini dikelola oleh Backend API melalui aplikasi Mobile RFC.
            Jika memerlukan perubahan data, silakan hubungi administrator atau akses melalui aplikasi Mobile RFC.
        </p>
    </div>

    {{-- Tab Navigation --}}
    <div class="border-b border-gray-200">
        <nav class="flex gap-1" role="tablist">
            <button @click="activeTab = 'users'"
                    :class="activeTab === 'users'
                        ? 'border-green-500 text-green-600 bg-green-50'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="px-5 py-3 text-sm font-medium border-b-2 rounded-t-lg transition-all"
                    role="tab"
                    :aria-selected="activeTab === 'users'">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                        <circle cx="9" cy="7" r="4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                    Daftar Pengguna
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full"
                          :class="activeTab === 'users' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                          x-text="filteredUsers.length">
                    </span>
                </span>
            </button>

            <button @click="activeTab = 'kebun'"
                    :class="activeTab === 'kebun'
                        ? 'border-green-500 text-green-600 bg-green-50'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="px-5 py-3 text-sm font-medium border-b-2 rounded-t-lg transition-all"
                    role="tab"
                    :aria-selected="activeTab === 'kebun'">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                        <circle cx="12" cy="10" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                    </svg>
                    Blok Kebun
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full"
                          :class="activeTab === 'kebun' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                          x-text="filteredKebun.length">
                    </span>
                </span>
            </button>
        </nav>
    </div>

    {{-- ============================================ --}}
    {{-- TAB 1: Daftar Pengguna                       --}}
    {{-- ============================================ --}}
    <div x-show="activeTab === 'users'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">

        {{-- Toolbar: Search + Filter --}}
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between mb-4">
            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                {{-- Search --}}
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           x-model="userSearch"
                           placeholder="Cari nama atau email..."
                           class="pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-500/15 w-full sm:w-72 min-h-[44px]">
                </div>

                {{-- Filter Role --}}
                <select x-model="userRoleFilter"
                        class="px-4 py-2.5 text-sm border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-500/15 min-h-[44px]">
                    <option value="">Semua Role</option>
                    @foreach($roleOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Result count --}}
            <p class="text-sm text-gray-500">
                <span x-text="filteredUsers.length"></span> pengguna ditemukan
            </p>
        </div>

        {{-- User Table --}}
        <x-card>
            <div class="overflow-x-auto -m-5">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-5 font-semibold text-gray-600 text-xs uppercase tracking-wider">No</th>
                            <th class="text-left py-3 px-5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Nama</th>
                            <th class="text-left py-3 px-5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Email</th>
                            <th class="text-left py-3 px-5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Role</th>
                            <th class="text-center py-3 px-5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(user, index) in filteredUsers" :key="user.id">
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-5 text-gray-500" x-text="index + 1"></td>
                                <td class="py-3 px-5">
                                    <span class="font-medium text-gray-900" x-text="user.nama"></span>
                                </td>
                                <td class="py-3 px-5 text-gray-600" x-text="user.email"></td>
                                <td class="py-3 px-5">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                          :class="roleBadgeClass(user.role)"
                                          x-text="roleLabel(user.role)">
                                    </span>
                                </td>
                                <td class="py-3 px-5 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                          :class="user.status === 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                          x-text="user.status === 1 ? 'Aktif' : 'Nonaktif'">
                                    </span>
                                </td>
                            </tr>
                        </template>

                        {{-- Empty State --}}
                        <template x-if="filteredUsers.length === 0">
                            <tr>
                                <td colspan="5" class="py-12 text-center">
                                    <div class="max-w-[300px] mx-auto">
                                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                        </svg>
                                        <p class="text-gray-500 text-sm">Tidak ada pengguna yang sesuai dengan filter.</p>
                                        <button @click="userSearch = ''; userRoleFilter = ''"
                                                class="mt-2 text-sm text-green-600 hover:text-green-700 font-medium">
                                            Reset Filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- ============================================ --}}
    {{-- TAB 2: Blok Kebun                            --}}
    {{-- ============================================ --}}
    <div x-show="activeTab === 'kebun'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">

        {{-- Toolbar: Search + Filter --}}
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between mb-4">
            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                {{-- Search --}}
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           x-model="kebunSearch"
                           placeholder="Cari nama atau lokasi..."
                           class="pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-500/15 w-full sm:w-72 min-h-[44px]">
                </div>

                {{-- Filter Jenis Budidaya --}}
                <select x-model="kebunJenisFilter"
                        class="px-4 py-2.5 text-sm border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-500/15 min-h-[44px]">
                    <option value="">Semua Jenis</option>
                    @foreach($jenisBudidayaOptions as $id => $nama)
                        <option value="{{ $nama }}">{{ $nama }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Result count --}}
            <p class="text-sm text-gray-500">
                <span x-text="filteredKebun.length"></span> blok kebun ditemukan
            </p>
        </div>

        {{-- Blok Kebun Table --}}
        <x-card>
            <div class="overflow-x-auto -m-5">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-5 font-semibold text-gray-600 text-xs uppercase tracking-wider">No</th>
                            <th class="text-left py-3 px-5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Nama Blok</th>
                            <th class="text-left py-3 px-5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Lokasi</th>
                            <th class="text-left py-3 px-5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Jenis Budidaya</th>
                            <th class="text-right py-3 px-5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Luas (m²)</th>
                            <th class="text-right py-3 px-5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Kapasitas</th>
                            <th class="text-center py-3 px-5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(kebun, index) in filteredKebun" :key="kebun.id">
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-5 text-gray-500" x-text="index + 1"></td>
                                <td class="py-3 px-5">
                                    <div>
                                        <span class="font-medium text-gray-900" x-text="kebun.nama"></span>
                                        <p class="text-xs text-gray-400 mt-0.5" x-text="kebun.deskripsi"></p>
                                    </div>
                                </td>
                                <td class="py-3 px-5 text-gray-600" x-text="kebun.lokasi"></td>
                                <td class="py-3 px-5">
                                    <div>
                                        <span class="text-gray-900" x-text="kebun.jenisBudidaya"></span>
                                        <p class="text-xs text-gray-400 italic" x-text="kebun.namaLatin"></p>
                                    </div>
                                </td>
                                <td class="py-3 px-5 text-right text-gray-600" x-text="kebun.luas.toLocaleString('id-ID')"></td>
                                <td class="py-3 px-5 text-right text-gray-600" x-text="kebun.kapasitas.toLocaleString('id-ID')"></td>
                                <td class="py-3 px-5 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                          :class="kebun.status === 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                          x-text="kebun.status === 1 ? 'Aktif' : 'Nonaktif'">
                                    </span>
                                </td>
                            </tr>
                        </template>

                        {{-- Empty State --}}
                        <template x-if="filteredKebun.length === 0">
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <div class="max-w-[300px] mx-auto">
                                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                                            <circle cx="12" cy="10" r="3"/>
                                        </svg>
                                        <p class="text-gray-500 text-sm">Tidak ada blok kebun yang sesuai dengan filter.</p>
                                        <button @click="kebunSearch = ''; kebunJenisFilter = ''"
                                                class="mt-2 text-sm text-green-600 hover:text-green-700 font-medium">
                                            Reset Filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

</div>
@endsection

@push('scripts')
<script>
function dataMasterPage() {
    return {
        activeTab: 'users',

        // Search & Filter state
        userSearch: '',
        userRoleFilter: '',
        kebunSearch: '',
        kebunJenisFilter: '',

        // TODO: [DASH-02] Data dari server via Blade
        users: @json($users),
        blokKebun: @json($blokKebun),

        // Computed: filtered users
        get filteredUsers() {
            return this.users.filter(user => {
                const matchSearch = this.userSearch === '' ||
                    user.nama.toLowerCase().includes(this.userSearch.toLowerCase()) ||
                    user.email.toLowerCase().includes(this.userSearch.toLowerCase());
                const matchRole = this.userRoleFilter === '' ||
                    user.role === this.userRoleFilter;
                return matchSearch && matchRole;
            });
        },

        // Computed: filtered blok kebun
        get filteredKebun() {
            return this.blokKebun.filter(kebun => {
                const matchSearch = this.kebunSearch === '' ||
                    kebun.nama.toLowerCase().includes(this.kebunSearch.toLowerCase()) ||
                    kebun.lokasi.toLowerCase().includes(this.kebunSearch.toLowerCase());
                const matchJenis = this.kebunJenisFilter === '' ||
                    kebun.jenisBudidaya === this.kebunJenisFilter;
                return matchSearch && matchJenis;
            });
        },

        // Helper: role badge color
        roleBadgeClass(role) {
            const classes = {
                'pjawab': 'bg-purple-100 text-purple-700',
                'inventor': 'bg-blue-100 text-blue-700',
                'petugas': 'bg-amber-100 text-amber-700',
                'admin': 'bg-gray-100 text-gray-700',
            };
            return classes[role] || 'bg-gray-100 text-gray-700';
        },

        // Helper: role label
        roleLabel(role) {
            const labels = {
                'pjawab': 'Penanggung Jawab',
                'inventor': 'Pengelola RFC',
                'petugas': 'Petugas Perkebunan',
                'admin': 'Administrator',
            };
            return labels[role] || role;
        },
    };
}
</script>
@endpush
