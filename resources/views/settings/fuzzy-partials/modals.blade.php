{{-- ═══ MODAL: Add Variable ═══ --}}
<div x-show="modal === 'addVariable'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/40" @click="modal = null"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 z-10" @click.stop>
        <h3 class="text-lg font-bold text-gray-900 mb-4">Tambah Variabel</h3>
        <form action="{{ route('settings.fuzzy.variables.store') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama *</label>
                        <input type="text" name="name" required pattern="[a-z_]+" title="Hanya huruf kecil dan underscore (contoh: suhu, status_lingkungan)" placeholder="e.g. suhu" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                        <p class="text-[11px] text-gray-400 mt-1">Huruf kecil & underscore saja</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Group *</label>
                        <select name="group" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="lingkungan">Lingkungan</option>
                            <option value="kesehatan">Kesehatan</option>
                            <option value="kausalitas">Kausalitas</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe *</label>
                        <select name="type" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="input">Input</option>
                            <option value="output">Output</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                        <input type="text" name="unit" placeholder="°C, %, ppm" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                    <input type="text" name="description" placeholder="Keterangan variabel" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="button" @click="modal = null" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors border border-gray-200">Batal</button>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-[var(--color-primary)] rounded-xl hover:opacity-90 transition-opacity">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ MODAL: Edit Variable ═══ --}}
<div x-show="modal === 'editVariable'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/40" @click="modal = null"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 z-10" @click.stop>
        <h3 class="text-lg font-bold text-gray-900 mb-4">Edit Variabel</h3>
        <form :action="`{{ url('/settings/fuzzy/variables') }}/${editVar.id}`" method="POST">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama *</label>
                        <input type="text" name="name" x-model="editVar.name" required pattern="[a-z_]+" title="Hanya huruf kecil dan underscore" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                        <p class="text-[11px] text-gray-400 mt-1">Huruf kecil & underscore saja</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Group *</label>
                        <select name="group" x-model="editVar.group" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="lingkungan">Lingkungan</option>
                            <option value="kesehatan">Kesehatan</option>
                            <option value="kausalitas">Kausalitas</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe *</label>
                        <select name="type" x-model="editVar.type" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="input">Input</option>
                            <option value="output">Output</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                        <input type="text" name="unit" x-model="editVar.unit" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                    <input type="text" name="description" x-model="editVar.description" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="button" @click="modal = null" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors border border-gray-200">Batal</button>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-[var(--color-primary)] rounded-xl hover:opacity-90 transition-opacity">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ MODAL: Add Set ═══ --}}
<div x-show="modal === 'addSet'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/40" @click="modal = null"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 z-10" @click.stop>
        <h3 class="text-lg font-bold text-gray-900 mb-4">Tambah Membership Function</h3>
        <form action="{{ route('settings.fuzzy.sets.store') }}" method="POST" x-data="{ addShape: 'triangle' }" onsubmit="return validateSetForm(this)">
            @csrf
            <input type="hidden" name="variable_id" x-model="editSet.variable_id">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Set *</label>
                        <input type="text" name="name" required placeholder="e.g. Tinggi" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Shape *</label>
                        <select name="shape" x-model="addShape" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="triangle">Triangle (3 titik)</option>
                            <option value="trapezoid">Trapezoid (4 titik)</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-4 gap-3">
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">a (kaki kiri) *</label><input type="number" step="any" name="a" required class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm text-center focus:outline-none focus:border-[var(--color-primary)] transition-all"></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1" x-text="addShape === 'triangle' ? 'b (puncak) *' : 'b (plateau kiri) *'">b *</label><input type="number" step="any" name="b" required class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm text-center focus:outline-none focus:border-[var(--color-primary)] transition-all"></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1" x-text="addShape === 'triangle' ? 'c (kaki kanan) *' : 'c (plateau kanan) *'">c *</label><input type="number" step="any" name="c" required class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm text-center focus:outline-none focus:border-[var(--color-primary)] transition-all"></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1" :class="addShape === 'trapezoid' ? 'text-red-500' : ''">d (kaki kanan) <span x-show="addShape === 'trapezoid'">*</span></label><input type="number" step="any" name="d" :required="addShape === 'trapezoid'" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm text-center focus:outline-none focus:border-[var(--color-primary)] transition-all" placeholder="-"></div>
                </div>
                <div class="bg-blue-50 rounded-lg px-3 py-2 text-xs text-blue-700">💡 Syarat: a ≤ b ≤ c (≤ d untuk trapezoid). Nilai harus berurutan dari kiri ke kanan.</div>
            </div>
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="button" @click="modal = null" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors border border-gray-200">Batal</button>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-[var(--color-primary)] rounded-xl hover:opacity-90 transition-opacity">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ MODAL: Edit Set ═══ --}}
<div x-show="modal === 'editSet'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/40" @click="modal = null"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 z-10" @click.stop>
        <h3 class="text-lg font-bold text-gray-900 mb-4">Edit Membership Function</h3>
        <form :action="`{{ url('/settings/fuzzy/sets') }}/${editSet.id}`" method="POST" onsubmit="return validateSetForm(this)">
            @csrf @method('PUT')
            <input type="hidden" name="variable_id" x-model="editSet.variable_id">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama *</label>
                        <input type="text" name="name" x-model="editSet.name" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Shape *</label>
                        <select name="shape" x-model="editSet.shape" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="triangle">Triangle</option>
                            <option value="trapezoid">Trapezoid</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-4 gap-3">
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">a</label><input type="number" step="any" name="a" x-model="editSet.a" required class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm text-center focus:outline-none focus:border-[var(--color-primary)] transition-all"></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">b</label><input type="number" step="any" name="b" x-model="editSet.b" required class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm text-center focus:outline-none focus:border-[var(--color-primary)] transition-all"></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">c</label><input type="number" step="any" name="c" x-model="editSet.c" required class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm text-center focus:outline-none focus:border-[var(--color-primary)] transition-all"></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">d</label><input type="number" step="any" name="d" x-model="editSet.d" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm text-center focus:outline-none focus:border-[var(--color-primary)] transition-all"></div>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="button" @click="modal = null" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors border border-gray-200">Batal</button>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-[var(--color-primary)] rounded-xl hover:opacity-90 transition-opacity">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ MODAL: Add Rule ═══ --}}
<div x-show="modal === 'addRule'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/40" @click="modal = null"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 z-10 max-h-[90vh] overflow-y-auto" @click.stop>
        <h3 class="text-lg font-bold text-gray-900 mb-4">Tambah Rule</h3>
        <form action="{{ route('settings.fuzzy.rules.store') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Group *</label>
                        <select name="group" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="lingkungan">Lingkungan</option>
                            <option value="kesehatan">Kesehatan</option>
                            <option value="kausalitas">Kausalitas</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Operator *</label>
                        <select name="operator" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="AND">AND</option>
                            <option value="OR">OR</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Output Set *</label>
                        <select name="output_set_id" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Pilih Output</option>
                            @foreach($variables->where('type', 'output') as $outVar)
                                <optgroup label="{{ $outVar->name }}">
                                    @foreach($outVar->sets as $s)
                                        <option value="{{ $s->id }}">{{ $outVar->name }} → {{ $s->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diagnosis *</label>
                    <input type="text" name="diagnosis" required placeholder="Keterangan rule (contoh: Kondisi ideal)" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                </div>
                {{-- Dynamic conditions --}}
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-sm font-medium text-gray-700">Kondisi (IF) <span class="text-xs text-gray-400 font-normal">— min. 2 kondisi</span></label>
                        <button type="button" @click="addCondition()" class="text-xs text-[var(--color-primary)] hover:underline cursor-pointer bg-transparent border-none font-medium">+ Tambah Kondisi</button>
                    </div>
                    <template x-for="(cond, idx) in ruleConditions" :key="idx">
                        <div class="flex gap-3 mb-2 items-end">
                            <div class="flex-1">
                                <select :name="`conditions[${idx}][variable_id]`" x-model="cond.variable_id" required class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                                    <option value="">Variabel</option>
                                    @foreach($variables->where('type', 'input') as $v)
                                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <span class="text-xs text-gray-400 pb-2">IS</span>
                            <div class="flex-1">
                                <select :name="`conditions[${idx}][set_id]`" x-model="cond.set_id" required class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                                    <option value="">Set</option>
                                    <template x-for="s in getSetsForVariable(cond.variable_id)" :key="s.id">
                                        <option :value="s.id" x-text="s.name"></option>
                                    </template>
                                </select>
                            </div>
                            <button type="button" @click="removeCondition(idx)" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" :class="ruleConditions.length <= 1 ? 'opacity-30 pointer-events-none' : ''">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="button" @click="modal = null" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors border border-gray-200">Batal</button>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-[var(--color-primary)] rounded-xl hover:opacity-90 transition-opacity">Simpan Rule</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ MODAL: Edit Rule ═══ --}}
<div x-show="modal === 'editRule'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/40" @click="modal = null"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 z-10 max-h-[90vh] overflow-y-auto" @click.stop>
        <h3 class="text-lg font-bold text-gray-900 mb-4">Edit Rule</h3>
        <form :action="`{{ url('/settings/fuzzy/rules') }}/${editRule.id}`" method="POST">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Operator *</label>
                        <select name="operator" x-model="editRule.operator" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="AND">AND</option>
                            <option value="OR">OR</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Output Set *</label>
                        <select name="output_set_id" x-model="editRule.output_set_id" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            @foreach($variables->where('type', 'output') as $outVar)
                                <optgroup label="{{ $outVar->name }}">
                                    @foreach($outVar->sets as $s)
                                        <option value="{{ $s->id }}">{{ $outVar->name }} → {{ $s->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diagnosis *</label>
                    <input type="text" name="diagnosis" x-model="editRule.diagnosis" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-sm font-medium text-gray-700">Kondisi (IF)</label>
                        <button type="button" @click="addCondition()" class="text-xs text-[var(--color-primary)] hover:underline cursor-pointer bg-transparent border-none font-medium">+ Tambah</button>
                    </div>
                    <template x-for="(cond, idx) in ruleConditions" :key="idx">
                        <div class="flex gap-3 mb-2 items-end">
                            <div class="flex-1">
                                <select :name="`conditions[${idx}][variable_id]`" x-model="cond.variable_id" required class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white">
                                    <option value="">Variabel</option>
                                    @foreach($variables->where('type', 'input') as $v)
                                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <span class="text-xs text-gray-400 pb-2">IS</span>
                            <div class="flex-1">
                                <select :name="`conditions[${idx}][set_id]`" x-model="cond.set_id" required class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white">
                                    <option value="">Set</option>
                                    <template x-for="s in getSetsForVariable(cond.variable_id)" :key="s.id">
                                        <option :value="s.id" x-text="s.name"></option>
                                    </template>
                                </select>
                            </div>
                            <button type="button" @click="removeCondition(idx)" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" :class="ruleConditions.length <= 1 ? 'opacity-30 pointer-events-none' : ''">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="button" @click="modal = null" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors border border-gray-200">Batal</button>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-[var(--color-primary)] rounded-xl hover:opacity-90 transition-opacity">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function validateSetForm(form) {
    const a = parseFloat(form.a.value);
    const b = parseFloat(form.b.value);
    const c = parseFloat(form.c.value);
    const d = form.d.value ? parseFloat(form.d.value) : null;
    const shape = form.shape.value;
    const errors = [];
    if (a > b) errors.push('a harus ≤ b');
    if (b > c) errors.push('b harus ≤ c');
    if (shape === 'trapezoid') {
        if (d === null) { errors.push('d wajib diisi untuk trapezoid'); }
        else if (c > d) { errors.push('c harus ≤ d'); }
    }
    if (errors.length > 0) {
        alert('Validasi parameter gagal:\n\n• ' + errors.join('\n• '));
        return false;
    }
    return true;
}
</script>
