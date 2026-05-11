{{-- Dialog Konfirmasi Hapus Sesi (SPK-02) --}}
<div x-show="deleteModal.open" x-cloak
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-end="opacity-0"
    @keydown.escape.window="deleteModal.open = false">

    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        @click.stop>

        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center shrink-0">
                <i data-lucide="trash-2" class="w-5 h-5 text-red-500"></i>
            </div>
            <h3 class="text-base font-bold text-gray-900">Hapus Sesi Evaluasi?</h3>
        </div>

        <p class="text-sm text-gray-600 mb-1">
            Anda akan menghapus sesi:
        </p>
        <p class="text-sm font-semibold text-gray-900 mb-4">
            <span x-text="deleteModal.nama"></span>
        </p>
        <p class="text-xs text-gray-500 mb-5">
            Data akan dihapus secara <em>soft delete</em> dan tidak akan muncul di daftar.
            Data tetap tersimpan di database untuk menjaga integritas historis SPK.
        </p>

        <div class="flex gap-3 justify-end">
            <button @click="deleteModal.open = false"
                class="px-4 py-2 border-2 border-gray-200 text-gray-600 font-semibold rounded-xl text-sm min-h-[40px] hover:border-gray-300 transition-all">
                Batal
            </button>
            <form :action="`/spk-melon/sesi-penilaian/${deleteModal.id}`" method="POST" @submit="deletingSubmit = true"
                  x-data="{ deletingSubmit: false }">
                @csrf
                @method('DELETE')
                <button type="submit" :disabled="deletingSubmit"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl text-sm min-h-[40px] transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                    x-text="deletingSubmit ? 'Menghapus...' : 'Ya, Hapus'">
                </button>
            </form>
        </div>
    </div>
</div>
