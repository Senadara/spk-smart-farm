<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SpkFuzzyVariable;
use App\Models\SpkFuzzySet;
use App\Models\SpkFuzzyRule;
use App\Models\SpkFuzzyRuleCondition;
use App\Models\SpkFuzzyInputSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class FuzzyConfigController extends Controller
{
    /**
     * Halaman utama konfigurasi Fuzzy Mamdani.
     */
    public function index()
    {
        $variables = SpkFuzzyVariable::with(['sets', 'inputSource'])
            ->orderByRaw("FIELD(`group`, 'lingkungan', 'kesehatan', 'kausalitas')")
            ->get();

        $rules = SpkFuzzyRule::with(['conditions.variable', 'conditions.set', 'outputSet.variable'])
            ->orderByRaw("FIELD(`group`, 'lingkungan', 'kesehatan', 'kausalitas')")
            ->get();

        $inputSources = SpkFuzzyInputSource::with('variable')->get();

        // Untuk dropdown di modal rule
        $allSets = SpkFuzzySet::with('variable')->get()->groupBy('variable_id');

        // Summary stats
        $stats = [
            'totalVariables' => $variables->count(),
            'totalSets'      => $variables->sum(fn($v) => $v->sets->count()),
            'totalRules'     => $rules->count(),
            'totalSources'   => $inputSources->count(),
        ];

        return view('settings.fuzzy', compact('variables', 'rules', 'inputSources', 'allSets', 'stats'));
    }

    // ── VARIABLES ────────────────────────────────────────────────

    public function storeVariable(Request $request)
    {
        $validated = $request->validate([
            'name'        => [
                'required', 'string', 'max:100', 'regex:/^[a-z_]+$/',
            ],
            'group'       => 'required|in:lingkungan,kesehatan,kausalitas',
            'type'        => 'required|in:input,output',
            'unit'        => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
        ], [
            'name.regex' => 'Nama variabel harus lowercase dan hanya boleh huruf kecil serta underscore (contoh: suhu, status_lingkungan).',
            'name.required' => 'Nama variabel wajib diisi.',
        ]);

        // Cek duplikat nama dalam group yang sama
        if (SpkFuzzyVariable::where('name', $validated['name'])->where('group', $validated['group'])->exists()) {
            return redirect()->route('settings.fuzzy.index')
                ->withErrors(['name' => "Variabel '{$validated['name']}' sudah ada di group '{$validated['group']}'."])
                ->withInput();
        }

        SpkFuzzyVariable::create($validated);

        return redirect()->route('settings.fuzzy.index')
            ->with('success', 'Variabel berhasil ditambahkan.');
    }

    public function updateVariable(Request $request, string $id)
    {
        $validated = $request->validate([
            'name'        => [
                'required', 'string', 'max:100', 'regex:/^[a-z_]+$/',
            ],
            'group'       => 'required|in:lingkungan,kesehatan,kausalitas',
            'type'        => 'required|in:input,output',
            'unit'        => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
        ], [
            'name.regex' => 'Nama variabel harus lowercase (huruf kecil + underscore).',
        ]);

        // Cek duplikat selain diri sendiri
        if (SpkFuzzyVariable::where('name', $validated['name'])->where('group', $validated['group'])->where('id', '!=', $id)->exists()) {
            return redirect()->route('settings.fuzzy.index')
                ->withErrors(['name' => "Variabel '{$validated['name']}' sudah ada di group '{$validated['group']}'."])
                ->withInput();
        }

        SpkFuzzyVariable::findOrFail($id)->update($validated);

        return redirect()->route('settings.fuzzy.index')
            ->with('success', 'Variabel berhasil diperbarui.');
    }

    public function destroyVariable(string $id)
    {
        $variable = SpkFuzzyVariable::findOrFail($id);

        // Cascade: hapus sets, input sources, rule conditions yang terkait
        SpkFuzzyRuleCondition::where('variable_id', $id)->delete();
        SpkFuzzyInputSource::where('variable_id', $id)->delete();
        // Hapus rules yang menggunakan sets dari variabel ini
        $setIds = SpkFuzzySet::where('variable_id', $id)->pluck('id');
        SpkFuzzyRule::whereIn('output_set_id', $setIds)->delete();
        SpkFuzzySet::where('variable_id', $id)->delete();
        $variable->delete();

        return redirect()->route('settings.fuzzy.index')
            ->with('success', "Variabel '{$variable->name}' beserta " . $setIds->count() . " sets & rules terkait berhasil dihapus.");
    }

    // ── SETS (MEMBERSHIP FUNCTIONS) ─────────────────────────────

    /**
     * Validasi custom: parameter a ≤ b ≤ c (≤ d untuk trapezoid).
     */
    private function validateSetParams(Request $request): array
    {
        $validated = $request->validate([
            'variable_id' => 'required|exists:spk_fuzzy_variables,id',
            'name'        => 'required|string|max:50',
            'shape'       => 'required|in:triangle,trapezoid',
            'a'           => 'required|numeric',
            'b'           => 'required|numeric',
            'c'           => 'required|numeric',
            'd'           => 'nullable|numeric',
        ], [
            'name.required' => 'Nama set wajib diisi.',
            'a.required' => 'Parameter a wajib diisi.',
            'b.required' => 'Parameter b wajib diisi.',
            'c.required' => 'Parameter c wajib diisi.',
        ]);

        $a = (float) $validated['a'];
        $b = (float) $validated['b'];
        $c = (float) $validated['c'];
        $d = isset($validated['d']) ? (float) $validated['d'] : null;

        $errors = [];

        if ($validated['shape'] === 'triangle') {
            if ($a > $b) $errors[] = 'Triangle: a harus ≤ b (a=' . $a . ', b=' . $b . ').';
            if ($b > $c) $errors[] = 'Triangle: b harus ≤ c (b=' . $b . ', c=' . $c . ').';
            // d harus kosong untuk triangle
            $validated['d'] = null;
        } else {
            // Trapezoid
            if ($d === null) {
                $errors[] = 'Trapezoid: parameter d wajib diisi.';
            } else {
                if ($a > $b) $errors[] = 'Trapezoid: a harus ≤ b (a=' . $a . ', b=' . $b . ').';
                if ($b > $c) $errors[] = 'Trapezoid: b harus ≤ c (b=' . $b . ', c=' . $c . ').';
                if ($c > $d) $errors[] = 'Trapezoid: c harus ≤ d (c=' . $c . ', d=' . $d . ').';
            }
        }

        if (!empty($errors)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'shape' => $errors,
            ]);
        }

        return $validated;
    }

    public function storeSet(Request $request)
    {
        $validated = $this->validateSetParams($request);

        // Cek duplikat nama dalam variabel yang sama
        if (SpkFuzzySet::where('variable_id', $validated['variable_id'])->where('name', $validated['name'])->exists()) {
            return redirect()->route('settings.fuzzy.index')
                ->withErrors(['name' => "Set '{$validated['name']}' sudah ada pada variabel ini."])
                ->withInput();
        }

        SpkFuzzySet::create($validated);

        return redirect()->route('settings.fuzzy.index')
            ->with('success', 'Membership function berhasil ditambahkan.');
    }

    public function updateSet(Request $request, string $id)
    {
        $validated = $this->validateSetParams($request);

        // Cek duplikat selain diri sendiri
        if (SpkFuzzySet::where('variable_id', $validated['variable_id'])->where('name', $validated['name'])->where('id', '!=', $id)->exists()) {
            return redirect()->route('settings.fuzzy.index')
                ->withErrors(['name' => "Set '{$validated['name']}' sudah ada pada variabel ini."])
                ->withInput();
        }

        SpkFuzzySet::findOrFail($id)->update($validated);

        return redirect()->route('settings.fuzzy.index')
            ->with('success', 'Membership function berhasil diperbarui.');
    }

    public function destroySet(string $id)
    {
        $set = SpkFuzzySet::findOrFail($id);

        // Cek apakah set sedang digunakan oleh rule
        $ruleCount = SpkFuzzyRule::where('output_set_id', $id)->count();
        $condCount = SpkFuzzyRuleCondition::where('set_id', $id)->count();

        if ($ruleCount > 0 || $condCount > 0) {
            return redirect()->route('settings.fuzzy.index')
                ->withErrors(['delete' => "Set '{$set->name}' masih digunakan oleh {$ruleCount} rule dan {$condCount} kondisi. Hapus rule terkait terlebih dahulu."]);
        }

        $set->delete();

        return redirect()->route('settings.fuzzy.index')
            ->with('success', 'Membership function berhasil dihapus.');
    }

    // ── RULES ───────────────────────────────────────────────────

    public function storeRule(Request $request)
    {
        $validated = $request->validate([
            'group'         => 'required|in:lingkungan,kesehatan,kausalitas',
            'operator'      => 'required|in:AND,OR',
            'output_set_id' => 'required|exists:spk_fuzzy_sets,id',
            'diagnosis'     => 'required|string|max:255',
            'conditions'    => 'required|array|min:2',
            'conditions.*.variable_id' => 'required|exists:spk_fuzzy_variables,id',
            'conditions.*.set_id'      => 'required|exists:spk_fuzzy_sets,id',
        ], [
            'diagnosis.required' => 'Diagnosis/keterangan rule wajib diisi.',
            'conditions.min'     => 'Rule harus memiliki minimal 2 kondisi.',
            'conditions.*.variable_id.required' => 'Variabel kondisi wajib dipilih.',
            'conditions.*.set_id.required'      => 'Set kondisi wajib dipilih.',
        ]);

        // Validasi: tidak boleh ada variabel kondisi duplikat
        $varIds = array_column($validated['conditions'], 'variable_id');
        if (count($varIds) !== count(array_unique($varIds))) {
            return redirect()->route('settings.fuzzy.index')
                ->withErrors(['conditions' => 'Tidak boleh ada variabel yang duplikat dalam satu rule.'])
                ->withInput();
        }

        DB::transaction(function () use ($validated) {
            $ruleCount = SpkFuzzyRule::where('group', $validated['group'])->count();
            $rule = SpkFuzzyRule::create([
                'name'          => "Rule-{$validated['group']}-" . ($ruleCount + 1),
                'operator'      => $validated['operator'],
                'output_set_id' => $validated['output_set_id'],
                'group'         => $validated['group'],
                'diagnosis'     => $validated['diagnosis'],
            ]);

            foreach ($validated['conditions'] as $cond) {
                SpkFuzzyRuleCondition::create([
                    'rule_id'     => $rule->id,
                    'variable_id' => $cond['variable_id'],
                    'set_id'      => $cond['set_id'],
                ]);
            }
        });

        return redirect()->route('settings.fuzzy.index')
            ->with('success', 'Rule berhasil ditambahkan.');
    }

    public function updateRule(Request $request, string $id)
    {
        $validated = $request->validate([
            'operator'      => 'required|in:AND,OR',
            'output_set_id' => 'required|exists:spk_fuzzy_sets,id',
            'diagnosis'     => 'required|string|max:255',
            'conditions'    => 'required|array|min:2',
            'conditions.*.variable_id' => 'required|exists:spk_fuzzy_variables,id',
            'conditions.*.set_id'      => 'required|exists:spk_fuzzy_sets,id',
        ], [
            'diagnosis.required' => 'Diagnosis/keterangan rule wajib diisi.',
            'conditions.min'     => 'Rule harus memiliki minimal 2 kondisi.',
        ]);

        // Validasi duplikat variabel
        $varIds = array_column($validated['conditions'], 'variable_id');
        if (count($varIds) !== count(array_unique($varIds))) {
            return redirect()->route('settings.fuzzy.index')
                ->withErrors(['conditions' => 'Tidak boleh ada variabel yang duplikat dalam satu rule.'])
                ->withInput();
        }

        DB::transaction(function () use ($id, $validated) {
            $rule = SpkFuzzyRule::findOrFail($id);
            $rule->update([
                'operator'      => $validated['operator'],
                'output_set_id' => $validated['output_set_id'],
                'diagnosis'     => $validated['diagnosis'],
            ]);

            // Replace conditions
            SpkFuzzyRuleCondition::where('rule_id', $id)->delete();
            foreach ($validated['conditions'] as $cond) {
                SpkFuzzyRuleCondition::create([
                    'rule_id'     => $id,
                    'variable_id' => $cond['variable_id'],
                    'set_id'      => $cond['set_id'],
                ]);
            }
        });

        return redirect()->route('settings.fuzzy.index')
            ->with('success', 'Rule berhasil diperbarui.');
    }

    public function destroyRule(string $id)
    {
        SpkFuzzyRuleCondition::where('rule_id', $id)->delete();
        SpkFuzzyRule::findOrFail($id)->delete();

        return redirect()->route('settings.fuzzy.index')
            ->with('success', 'Rule berhasil dihapus.');
    }

    // ── RESET ───────────────────────────────────────────────────

    /**
     * Reset seluruh konfigurasi fuzzy ke default (jalankan seeder).
     */
    public function resetToDefault()
    {
        Artisan::call('db:seed', ['--class' => 'SpkFuzzySeeder', '--force' => true]);

        return redirect()->route('settings.fuzzy.index')
            ->with('success', 'Konfigurasi fuzzy berhasil di-reset ke default.');
    }
}
