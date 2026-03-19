@extends('layouts.app')

@section('title', 'Data Master Operasional')

@section('content')
<div x-data="dataMasterPage()" x-init="init()" class="space-y-6">

    {{-- Page Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Data Master Operasional</h1>
        <p class="mt-1 text-sm text-gray-500">
            Informasi pengguna dan blok kebun yang terdaftar di sistem RFC.
            Data bersifat <span class="italic font-medium">read-only</span> dan dikelola melalui aplikasi Mobile RFC.
        </p>
    </div>

    {{-- Info Banner (read-only notice) --}}
    <div class="flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl shadow-sm">
        <i data-lucide="info" class="w-5 h-5 text-amber-500 mt-0.5 shrink-0"></i>
        <p class="text-sm text-amber-800 leading-relaxed">
            Data pada halaman ini dikelola oleh Backend API melalui aplikasi Mobile RFC.
            Jika memerlukan perubahan data, silakan hubungi administrator atau akses melalui aplikasi Mobile RFC.
        </p>
    </div>

    {{-- Tab Navigation --}}
    <div class="flex border-b border-gray-200 mb-6">
        <button @click="activeTab = 'users'"
                :class="activeTab === 'users'
                    ? 'border-emerald-500 text-emerald-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition-all"
                role="tab"
                :aria-selected="activeTab === 'users'">
            <i data-lucide="users" class="w-4 h-4"></i>
            Daftar Pengguna
            <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full transition-colors"
                  :class="activeTab === 'users' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                  x-text="filteredUsers.length">
            </span>
        </button>

        <button @click="activeTab = 'kebun'"
                :class="activeTab === 'kebun'
                    ? 'border-emerald-500 text-emerald-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition-all"
                role="tab"
                :aria-selected="activeTab === 'kebun'">
            <i data-lucide="layout-grid" class="w-4 h-4"></i>
            Blok Kebun
            <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full transition-colors"
                  :class="activeTab === 'kebun' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                  x-text="filteredKebun.length">
            </span>
        </button>
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
                    <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                    <input type="text"
                           x-model="userSearch"
                           placeholder="Cari nama atau email..."
                           class="pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 w-full sm:w-72 min-h-[44px] transition-shadow shadow-sm">
                </div>

                {{-- Filter Role --}}
                <div class="relative">
                    <i data-lucide="filter" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                    <select x-model="userRoleFilter"
                            class="pl-10 pr-8 py-2.5 text-sm border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 min-h-[44px] transition-shadow shadow-sm appearance-none bg-white">
                        <option value="">Semua Role</option>
                        @foreach($roleOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <i data-lucide="chevron-down" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                </div>
            </div>

            {{-- Result count --}}
            <div class="text-sm text-gray-500 bg-gray-50 px-3.5 py-2 rounded-lg border border-gray-100 shadow-sm">
                <span x-text="filteredUsers.length" class="font-bold text-gray-900"></span> <span class="font-medium">pengguna ditemukan</span>
            </div>
        </div>

        {{-- User Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden transition-all hover:shadow-lg">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider">No</th>
                            <th class="text-left py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider">Nama</th>
                            <th class="text-left py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider">Email</th>
                            <th class="text-left py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider">Role</th>
                            <th class="text-center py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(user, index) in paginatedUsers" :key="user.id">
                            <tr class="border-b border-gray-50 last:border-none hover:bg-emerald-50/30 transition-colors">
                                <td class="py-4 px-6 text-gray-500 font-medium" x-text="(currentPageUsers - 1) * itemsPerPage + index + 1"></td>
                                <td class="py-4 px-6">
                                    <span class="font-semibold text-gray-900 bg-white" x-text="user.nama"></span>
                                </td>
                                <td class="py-4 px-6 text-gray-500" x-text="user.email"></td>
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase"
                                          :class="roleBadgeClass(user.role)"
                                          x-text="roleLabel(user.role)">
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase"
                                          :class="user.status === 1 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'"
                                          x-text="user.status === 1 ? 'Aktif' : 'Nonaktif'">
                                    </span>
                                </td>
                            </tr>
                        </template>

                        {{-- Empty State --}}
                        <template x-if="filteredUsers.length === 0">
                            <tr>
                                <td colspan="5" class="py-16 text-center bg-gray-50/50">
                                    <div class="max-w-[300px] mx-auto">
                                        <div class="w-16 h-16 bg-white shadow-sm rounded-full flex items-center justify-center mx-auto mb-4 border border-gray-100">
                                            <i data-lucide="users" class="w-8 h-8 text-gray-300"></i>
                                        </div>
                                        <p class="text-gray-900 font-semibold">Tidak ada pengguna ditemukan</p>
                                        <p class="text-gray-500 text-sm mt-1 mb-4">Ubah kata kunci pencarian atau filter role.</p>
                                        <button @click="userSearch = ''; userRoleFilter = ''"
                                                class="text-sm font-semibold text-emerald-600 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-4 py-2 rounded-lg transition-colors border border-emerald-100 shadow-sm">
                                            Reset Filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Pagination Footer --}}
            <div x-show="filteredUsers.length > 0" class="px-6 py-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4 bg-gray-50/30">
                <div class="text-xs text-gray-500 font-medium">
                    Menampilkan <span class="text-gray-900" x-text="((currentPageUsers - 1) * itemsPerPage) + 1"></span>
                    sampai <span class="text-gray-900" x-text="Math.min(currentPageUsers * itemsPerPage, filteredUsers.length)"></span>
                    dari <span class="text-gray-900" x-text="filteredUsers.length"></span> data
                </div>
                
                <div class="flex items-center gap-1.5" x-show="totalPagesUsers > 1">
                    <button @click="if(currentPageUsers > 1) currentPageUsers--"
                            :disabled="currentPageUsers === 1"
                            class="p-2 border border-gray-200 rounded-lg hover:bg-white hover:text-emerald-600 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-gray-400 transition-all shadow-sm"
                            title="Sebelumnya">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </button>
                    
                    <div class="flex items-center gap-1 mx-2">
                        <template x-for="p in totalPagesUsers">
                            <button @click="currentPageUsers = p"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all"
                                    :class="currentPageUsers === p ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200' : 'text-gray-500 hover:bg-white hover:text-emerald-600 border border-transparent'"
                                    x-text="p">
                            </button>
                        </template>
                    </div>

                    <button @click="if(currentPageUsers < totalPagesUsers) currentPageUsers++"
                            :disabled="currentPageUsers === totalPagesUsers"
                            class="p-2 border border-gray-200 rounded-lg hover:bg-white hover:text-emerald-600 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-gray-400 transition-all shadow-sm"
                            title="Selanjutnya">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>
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
                    <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                    <input type="text"
                           x-model="kebunSearch"
                           placeholder="Cari nama atau lokasi..."
                           class="pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 w-full sm:w-72 min-h-[44px] transition-shadow shadow-sm">
                </div>

                {{-- Filter Jenis Budidaya --}}
                <div class="relative">
                    <i data-lucide="filter" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                    <select x-model="kebunJenisFilter"
                            class="pl-10 pr-8 py-2.5 text-sm border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 min-h-[44px] transition-shadow shadow-sm appearance-none bg-white">
                        <option value="">Semua Jenis</option>
                        @foreach($jenisBudidayaOptions as $id => $nama)
                            <option value="{{ $nama }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                    <i data-lucide="chevron-down" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                </div>
            </div>

            {{-- Result count --}}
            <div class="text-sm text-gray-500 bg-gray-50 px-3.5 py-2 rounded-lg border border-gray-100 shadow-sm">
                <span x-text="filteredKebun.length" class="font-bold text-gray-900"></span> <span class="font-medium">blok kebun ditemukan</span>
            </div>
        </div>

        {{-- Blok Kebun Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden transition-all hover:shadow-lg">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">No</th>
                            <th class="text-left py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Nama Blok</th>
                            <th class="text-left py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Lokasi</th>
                            <th class="text-left py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Jenis Budidaya</th>
                            <th class="text-right py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Luas (m²)</th>
                            <th class="text-right py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Kapasitas</th>
                            <th class="text-center py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Peringkat Terakhir</th>
                            <th class="text-right py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Skor Terakhir</th>
                            <th class="text-center py-4 px-6 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(kebun, index) in paginatedKebun" :key="kebun.id">
                            <tr class="border-b border-gray-50 last:border-none hover:bg-emerald-50/30 transition-colors">
                                <td class="py-4 px-6 text-gray-500 font-medium" x-text="(currentPageKebun - 1) * itemsPerPage + index + 1"></td>
                                <td class="py-4 px-6 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-gray-900" x-text="kebun.nama"></span>
                                        <span class="text-[11px] text-gray-500 mt-0.5 max-w-[200px] truncate" x-text="kebun.deskripsi" :title="kebun.deskripsi"></span>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-gray-500 whitespace-nowrap" x-text="kebun.lokasi"></td>
                                <td class="py-4 px-6 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-gray-900" x-text="kebun.jenisBudidaya"></span>
                                        <span class="text-[11px] text-gray-400 italic" x-text="kebun.namaLatin"></span>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-right text-gray-600 font-medium" x-text="kebun.luas.toLocaleString('id-ID')"></td>
                                <td class="py-4 px-6 text-right text-gray-600 font-medium" x-text="kebun.kapasitas.toLocaleString('id-ID')"></td>
                                <td class="py-4 px-6 text-center">
                                    <template x-if="kebun.peringkat_terakhir !== null">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold shadow-sm"
                                              :class="kebun.peringkat_terakhir <= 2 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : (kebun.peringkat_terakhir <= 3 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-red-50 text-red-700 border border-red-200')"
                                              x-text="'#' + kebun.peringkat_terakhir">
                                        </span>
                                    </template>
                                    <template x-if="kebun.peringkat_terakhir === null">
                                        <span class="text-xs font-bold text-gray-300">—</span>
                                    </template>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <template x-if="kebun.skor_terakhir !== null">
                                        <span class="text-sm font-bold text-gray-900" x-text="kebun.skor_terakhir.toFixed(4)"></span>
                                    </template>
                                    <template x-if="kebun.skor_terakhir === null">
                                        <span class="text-xs font-bold text-gray-300">—</span>
                                    </template>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase"
                                          :class="kebun.status === 1 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'"
                                          x-text="kebun.status === 1 ? 'Aktif' : 'Nonaktif'">
                                    </span>
                                </td>
                            </tr>
                        </template>

                        {{-- Empty State --}}
                        <template x-if="filteredKebun.length === 0">
                            <tr>
                                <td colspan="9" class="py-16 text-center bg-gray-50/50">
                                    <div class="max-w-[300px] mx-auto">
                                        <div class="w-16 h-16 bg-white shadow-sm rounded-full flex items-center justify-center mx-auto mb-4 border border-gray-100">
                                            <i data-lucide="layout-grid" class="w-8 h-8 text-gray-300"></i>
                                        </div>
                                        <p class="text-gray-900 font-semibold">Tidak ada blok kebun ditemukan</p>
                                        <p class="text-gray-500 text-sm mt-1 mb-4">Ubah kata kunci pencarian atau filter jenis budidaya.</p>
                                        <button @click="kebunSearch = ''; kebunJenisFilter = ''"
                                                class="text-sm font-semibold text-emerald-600 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-4 py-2 rounded-lg transition-colors border border-emerald-100 shadow-sm">
                                            Reset Filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Pagination Footer --}}
            <div x-show="filteredKebun.length > 0" class="px-6 py-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4 bg-gray-50/30">
                <div class="text-xs text-gray-500 font-medium">
                    Menampilkan <span class="text-gray-900" x-text="((currentPageKebun - 1) * itemsPerPage) + 1"></span>
                    sampai <span class="text-gray-900" x-text="Math.min(currentPageKebun * itemsPerPage, filteredKebun.length)"></span>
                    dari <span class="text-gray-900" x-text="filteredKebun.length"></span> data
                </div>
                
                <div class="flex items-center gap-1.5" x-show="totalPagesKebun > 1">
                    <button @click="if(currentPageKebun > 1) currentPageKebun--"
                            :disabled="currentPageKebun === 1"
                            class="p-2 border border-gray-200 rounded-lg hover:bg-white hover:text-emerald-600 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-gray-400 transition-all shadow-sm"
                            title="Sebelumnya">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </button>
                    
                    <div class="flex items-center gap-1 mx-2">
                        <template x-for="p in totalPagesKebun">
                            <button @click="currentPageKebun = p"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all"
                                    :class="currentPageKebun === p ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200' : 'text-gray-500 hover:bg-white hover:text-emerald-600 border border-transparent'"
                                    x-text="p">
                            </button>
                        </template>
                    </div>

                    <button @click="if(currentPageKebun < totalPagesKebun) currentPageKebun++"
                            :disabled="currentPageKebun === totalPagesKebun"
                            class="p-2 border border-gray-200 rounded-lg hover:bg-white hover:text-emerald-600 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-gray-400 transition-all shadow-sm"
                            title="Selanjutnya">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>
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

        // Pagination state
        currentPageUsers: 1,
        currentPageKebun: 1,
        itemsPerPage: 10,

        // TODO: [DASH-02] Data dari server via Blade
        users: @json($users),
        blokKebun: @json($blokKebun),

        init() {
            this.$watch('userSearch', () => { this.currentPageUsers = 1; });
            this.$watch('userRoleFilter', () => { this.currentPageUsers = 1; });
            this.$watch('kebunSearch', () => { this.currentPageKebun = 1; });
            this.$watch('kebunJenisFilter', () => { this.currentPageKebun = 1; });

            this.$watch('filteredUsers', () => { this.$nextTick(() => { if(typeof lucide !== 'undefined') lucide.createIcons(); }); });
            this.$watch('filteredKebun', () => { this.$nextTick(() => { if(typeof lucide !== 'undefined') lucide.createIcons(); }); });
            this.$watch('activeTab', () => { this.$nextTick(() => { if(typeof lucide !== 'undefined') lucide.createIcons(); }); });
            this.$nextTick(() => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        },

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

        // PAGINATION COMPUTED
        get paginatedUsers() {
            const start = (this.currentPageUsers - 1) * this.itemsPerPage;
            return this.filteredUsers.slice(start, start + this.itemsPerPage);
        },

        get totalPagesUsers() {
            return Math.ceil(this.filteredUsers.length / this.itemsPerPage);
        },

        get paginatedKebun() {
            const start = (this.currentPageKebun - 1) * this.itemsPerPage;
            return this.filteredKebun.slice(start, start + this.itemsPerPage);
        },

        get totalPagesKebun() {
            return Math.ceil(this.filteredKebun.length / this.itemsPerPage);
        },

        // Helper: role badge color
        roleBadgeClass(role) {
            const classes = {
                'pjawab': 'bg-purple-50 text-purple-700 border border-purple-200',
                'inventor': 'bg-blue-50 text-blue-700 border border-blue-200',
                'petugas': 'bg-amber-50 text-amber-700 border border-amber-200',
                'admin': 'bg-gray-50 text-gray-700 border border-gray-200',
            };
            return classes[role] || 'bg-gray-50 text-gray-700 border border-gray-200';
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
