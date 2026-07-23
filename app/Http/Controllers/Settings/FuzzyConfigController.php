<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\IotParameter;
use App\Models\SpkFuzzyProfile;
use App\Models\SpkFuzzyVariable;
use App\Models\SpkFuzzySet;
use App\Models\SpkFuzzyRule;
use App\Models\SpkFuzzyRuleCondition;
use App\Models\SpkFuzzyInputSource;
use App\Services\LivestockMasterConfigService;
use App\Services\Fuzzy\FuzzyProfileTemplateService;
use App\Services\Fuzzy\LayerChickenFuzzyDefaultTemplateService;
use App\Services\Fuzzy\MamdaniEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Validator;

class FuzzyConfigController extends Controller
{
    public function __construct(
        protected LivestockMasterConfigService $livestockMasterConfigService,
        protected FuzzyProfileTemplateService $fuzzyProfileTemplateService
    ) {}

    /**
     * Halaman utama konfigurasi Fuzzy Mamdani.
     */
    public function index(Request $request)
    {
        $livestockCommodityIds = $this->livestockMasterConfigService->livestockCommodityIds();
        $livestockJenisBudidayaIds = $this->livestockMasterConfigService->livestockJenisBudidayaIds();
        $profileHasJenisColumn = Schema::hasColumn('spk_fuzzy_profiles', 'jenis_budidaya_id');
        $profiles = SpkFuzzyProfile::with(['commodity', 'jenisBudidaya'])
            ->when(! empty($livestockJenisBudidayaIds) || ! empty($livestockCommodityIds), function ($query) use ($livestockJenisBudidayaIds, $livestockCommodityIds, $profileHasJenisColumn) {
                $query->where(function ($inner) use ($livestockCommodityIds) {
                    if (! empty($livestockCommodityIds)) {
                        $inner->whereIn('commodity_id', $livestockCommodityIds)
                            ->orWhereNull('commodity_id');
                    } else {
                        $inner->whereNull('commodity_id');
                    }
                });
                if ($profileHasJenisColumn) {
                    $query->orWhere(function ($inner) use ($livestockJenisBudidayaIds) {
                        if (! empty($livestockJenisBudidayaIds)) {
                            $inner->whereIn('jenis_budidaya_id', $livestockJenisBudidayaIds)
                                ->orWhereNull('jenis_budidaya_id');
                        } else {
                            $inner->whereNull('jenis_budidaya_id');
                        }
                    });
                }
            }, fn ($query) => $query->whereNull('commodity_id'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $livestockTypes = $this->livestockMasterConfigService->livestockTypeOptions();
        $requestedJenisBudidayaId = $request->filled('jenis_budidaya_id')
            ? $this->livestockMasterConfigService->resolveLivestockJenisBudidayaId($request->query('jenis_budidaya_id'))
            : null;

        $activeProfile = null;

        if ($request->filled('profile_id')) {
            $activeProfile = $profiles->firstWhere('id', $request->query('profile_id'));
        } elseif ($requestedJenisBudidayaId) {
            $profilesForJenis = $profiles->where('jenis_budidaya_id', $requestedJenisBudidayaId)->values();
            $activeProfile = $profilesForJenis->firstWhere('is_active', true) ?: $profilesForJenis->first();
        } else {
            $activeProfile = $profiles->firstWhere('is_active', true) ?: $profiles->first();
        }

        $activeProfileId = $activeProfile?->id;
        $syncSummary = $activeProfile ? $this->fuzzyProfileTemplateService->syncFromMaster($activeProfile) : null;

        if ($activeProfile) {
            $activeProfile->refresh()->load(['commodity', 'jenisBudidaya']);
        }

        $activeJenisBudidayaId = $requestedJenisBudidayaId
            ?: $activeProfile?->jenis_budidaya_id
            ?: SpkFuzzyProfile::resolveJenisBudidayaIdFromCommodity($activeProfile?->commodity_id);
        $activeCommodityId = $activeProfile?->commodity_id
            ?: $this->livestockMasterConfigService->resolveLivestockCommodityIdForJenis($activeJenisBudidayaId);

        $variables = SpkFuzzyVariable::with(['sets', 'inputSource'])
            ->when($activeProfileId, fn ($query) => $query->where('profile_id', $activeProfileId), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByRaw("FIELD(`group`, 'lingkungan', 'kesehatan', 'kausalitas')")
            ->get();

        $rules = SpkFuzzyRule::with(['conditions.variable', 'conditions.set', 'outputSet.variable'])
            ->when($activeProfileId, fn ($query) => $query->where('profile_id', $activeProfileId), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByRaw("FIELD(`group`, 'lingkungan', 'kesehatan', 'kausalitas')")
            ->get();

        $inputSources = SpkFuzzyInputSource::with('variable')
            ->when($activeProfileId, fn ($query) => $query->where('profile_id', $activeProfileId), fn ($query) => $query->whereRaw('1 = 0'))
            ->get();

        // Untuk dropdown di modal rule
        $allSets = SpkFuzzySet::with('variable')
            ->whereIn('variable_id', $variables->pluck('id'))
            ->get()
            ->groupBy('variable_id');

        $commodities = $this->livestockMasterConfigService->livestockCommodities();
        $iotParameters = $this->livestockMasterConfigService->configuredIotParametersForJenis($activeJenisBudidayaId, false);
        $availableFunctions = $this->availableSourceFunctions($activeCommodityId);
        $databaseSources = $this->allowedDatabaseFields();
        $masterConfigStatus = $activeJenisBudidayaId
            ? $this->livestockMasterConfigService->readinessForJenis($activeJenisBudidayaId)
            : [
                'configured' => false,
                'title' => 'Belum ada jenis ternak',
                'message' => 'Buat atau pilih template fuzzy untuk jenis ternak terlebih dahulu.',
                'environment_count' => 0,
                'function_count' => 0,
                'hints' => [],
            ];
        $masterConfigStatus['data_master_url'] = route('data-master.index', array_filter([
            'jenis_budidaya_id' => $activeJenisBudidayaId,
        ]));
        $masterVariableNames = $syncSummary['master_variable_names'] ?? [];
        $templateAssignments = $livestockTypes->map(function ($type) use ($profiles) {
            $typeProfiles = $profiles->where('jenis_budidaya_id', $type->id)->values();
            $activeTemplate = $typeProfiles->firstWhere('is_active', true);

            return (object) [
                'jenis_budidaya_id' => $type->id,
                'jenis_budidaya_nama' => $type->nama,
                'primary_commodity_id' => $type->primary_commodity_id ?? null,
                'template_count' => $typeProfiles->count(),
                'active_profile' => $activeTemplate,
                'profiles' => $typeProfiles,
                'draft_count' => $typeProfiles->where('status', 'draft')->count(),
                'review_count' => $typeProfiles->where('status', 'review')->count(),
                'archived_count' => $typeProfiles->where('status', 'archived')->count(),
            ];
        });

        // Summary stats
        $stats = [
            'totalProfiles'  => $profiles->count(),
            'totalVariables' => $variables->count(),
            'totalSets'      => $variables->sum(fn($v) => $v->sets->count()),
            'totalRules'     => $rules->count(),
            'totalSources'   => $inputSources->count(),
        ];

        return view('settings.fuzzy', compact(
            'variables',
            'rules',
            'inputSources',
            'allSets',
            'stats',
            'profiles',
            'activeProfile',
            'activeProfileId',
            'livestockTypes',
            'activeJenisBudidayaId',
            'activeCommodityId',
            'commodities',
            'iotParameters',
            'availableFunctions',
            'databaseSources',
            'masterConfigStatus',
            'syncSummary',
            'masterVariableNames',
            'templateAssignments'
        ));
    }

    public function storeProfile(Request $request)
    {
        $validated = $request->validate([
            'jenis_budidaya_id' => 'required|string|exists:jenisBudidaya,id',
            'commodity_id' => 'nullable|exists:komoditas,id',
            'name' => 'required|string|max:150',
            'version' => 'required|string|max:30',
            'status' => 'required|in:draft,review,active,archived',
            'reviewed_by' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:1000',
        ]);

        $jenisBudidayaId = $this->livestockMasterConfigService
            ->resolveLivestockJenisBudidayaId($validated['jenis_budidaya_id']);

        if (! $jenisBudidayaId || $jenisBudidayaId !== $validated['jenis_budidaya_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'jenis_budidaya_id' => 'Template Fuzzy hanya boleh memakai jenis budidaya bertipe hewan.',
            ]);
        }

        $validated['jenis_budidaya_id'] = $jenisBudidayaId;
        $validated['commodity_id'] = $this->livestockMasterConfigService
            ->resolveLivestockCommodityIdForJenis($jenisBudidayaId, $validated['commodity_id'] ?? null);
        $validated['is_active'] = $validated['status'] === 'active';
        if ($validated['status'] === 'active' && ! empty($validated['reviewed_by'])) {
            $validated['reviewed_at'] = now();
        }

        $profile = DB::transaction(function () use ($validated) {
            if ($validated['is_active']) {
                $activeQuery = SpkFuzzyProfile::query();
                if (Schema::hasColumn('spk_fuzzy_profiles', 'jenis_budidaya_id')) {
                    $activeQuery->where('jenis_budidaya_id', $validated['jenis_budidaya_id']);
                } else {
                    $activeQuery->where('commodity_id', $validated['commodity_id']);
                }
                $activeQuery->update(['is_active' => false]);
            }

            return SpkFuzzyProfile::create($validated);
        });

        $this->fuzzyProfileTemplateService->syncFromMaster($profile);
        MamdaniEngine::clearCache($profile->id);

        return redirect()->route('settings.fuzzy.index', [
            'profile_id' => $profile->id,
            'jenis_budidaya_id' => $profile->jenis_budidaya_id,
            'tab' => 'variables',
        ])
            ->with('success', 'Template fuzzy berhasil dibuat.');
    }

    public function activateProfile(string $id)
    {
        $profile = SpkFuzzyProfile::findOrFail($id);

        DB::transaction(function () use ($profile) {
            $activeQuery = SpkFuzzyProfile::query();
            if (Schema::hasColumn('spk_fuzzy_profiles', 'jenis_budidaya_id') && $profile->jenis_budidaya_id) {
                $activeQuery->where('jenis_budidaya_id', $profile->jenis_budidaya_id);
            } else {
                $activeQuery->where('commodity_id', $profile->commodity_id);
            }
            $activeQuery->update(['is_active' => false]);
            $profile->update([
                'status' => 'active',
                'is_active' => true,
                'reviewed_at' => $profile->reviewed_at ?: now(),
            ]);
        });

        $this->fuzzyProfileTemplateService->syncFromMaster($profile);
        MamdaniEngine::clearCache($profile->id);

        return redirect()->route('settings.fuzzy.index', [
            'profile_id' => $profile->id,
            'jenis_budidaya_id' => $profile->jenis_budidaya_id,
            'tab' => 'variables',
        ])
            ->with('success', "Template '{$profile->name}' berhasil dijadikan aktif.");
    }

    // ── VARIABLES ────────────────────────────────────────────────

    public function activateTemplateForJenis(Request $request)
    {
        $validated = $request->validate([
            'jenis_budidaya_id' => 'required|string|exists:jenisBudidaya,id',
            'profile_id' => 'required|string|exists:spk_fuzzy_profiles,id',
        ]);

        $jenisBudidayaId = $this->livestockMasterConfigService
            ->resolveLivestockJenisBudidayaId($validated['jenis_budidaya_id']);

        if (! $jenisBudidayaId || $jenisBudidayaId !== $validated['jenis_budidaya_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'jenis_budidaya_id' => 'Template Fuzzy hanya boleh diaktifkan untuk jenis budidaya bertipe hewan.',
            ]);
        }

        $profile = SpkFuzzyProfile::findOrFail($validated['profile_id']);

        if ($profile->jenis_budidaya_id && $profile->jenis_budidaya_id !== $jenisBudidayaId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'profile_id' => 'Template ini sudah terhubung dengan jenis ternak lain. Buat profil baru untuk jenis ternak yang dipilih.',
            ]);
        }

        if ($profile->status === 'archived') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'profile_id' => 'Template yang sudah diarsipkan tidak bisa dijadikan aktif.',
            ]);
        }

        $commodityId = $this->livestockMasterConfigService
            ->resolveLivestockCommodityIdForJenis($jenisBudidayaId, $profile->commodity_id);

        DB::transaction(function () use ($profile, $jenisBudidayaId, $commodityId) {
            $activeQuery = SpkFuzzyProfile::query()
                ->where('id', '<>', $profile->id);

            if (Schema::hasColumn('spk_fuzzy_profiles', 'jenis_budidaya_id')) {
                $activeQuery->where('jenis_budidaya_id', $jenisBudidayaId);
            } else {
                $activeQuery->where('commodity_id', $commodityId);
            }

            $activeQuery->update(['is_active' => false]);

            $profile->forceFill([
                'jenis_budidaya_id' => $jenisBudidayaId,
                'commodity_id' => $commodityId,
                'status' => 'active',
                'is_active' => true,
                'reviewed_at' => $profile->reviewed_at ?: now(),
            ])->save();
        });

        $this->fuzzyProfileTemplateService->syncFromMaster($profile);
        MamdaniEngine::clearCache($profile->id);

        return redirect()->route('settings.fuzzy.index', [
            'profile_id' => $profile->id,
            'jenis_budidaya_id' => $jenisBudidayaId,
            'tab' => 'variables',
        ])->with('success', "Template '{$profile->name}' aktif untuk jenis ternak terkait.");
    }

    public function storeVariable(Request $request)
    {
        $validated = $request->validate([
            'profile_id'   => 'required|exists:spk_fuzzy_profiles,id',
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
        if (SpkFuzzyVariable::where('profile_id', $validated['profile_id'])->where('name', $validated['name'])->where('group', $validated['group'])->exists()) {
            return redirect()->route('settings.fuzzy.index', ['profile_id' => $validated['profile_id'], 'tab' => 'variables'])
                ->withErrors(['name' => "Variabel '{$validated['name']}' sudah ada di group '{$validated['group']}'."])
                ->withInput();
        }

        SpkFuzzyVariable::create($validated);
        MamdaniEngine::clearCache($validated['profile_id']);

        return redirect()->route('settings.fuzzy.index', ['profile_id' => $validated['profile_id']])
            ->with('success', 'Variabel berhasil ditambahkan.');
    }

    public function updateVariable(Request $request, string $id)
    {
        $validated = $request->validate([
            'profile_id'   => 'required|exists:spk_fuzzy_profiles,id',
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
        if (SpkFuzzyVariable::where('profile_id', $validated['profile_id'])->where('name', $validated['name'])->where('group', $validated['group'])->where('id', '!=', $id)->exists()) {
            return redirect()->route('settings.fuzzy.index', ['profile_id' => $validated['profile_id'], 'tab' => 'variables'])
                ->withErrors(['name' => "Variabel '{$validated['name']}' sudah ada di group '{$validated['group']}'."])
                ->withInput();
        }

        SpkFuzzyVariable::findOrFail($id)->update($validated);
        MamdaniEngine::clearCache($validated['profile_id']);

        return redirect()->route('settings.fuzzy.index', ['profile_id' => $validated['profile_id'], 'tab' => 'variables'])
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
        MamdaniEngine::clearCache($variable->profile_id);

        return redirect()->route('settings.fuzzy.index', ['profile_id' => $variable->profile_id])
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
        $variable = SpkFuzzyVariable::findOrFail($validated['variable_id']);

        // Cek duplikat nama dalam variabel yang sama
        if (SpkFuzzySet::where('variable_id', $validated['variable_id'])->where('name', $validated['name'])->exists()) {
            return redirect()->route('settings.fuzzy.index', ['profile_id' => $variable->profile_id])
                ->withErrors(['name' => "Set '{$validated['name']}' sudah ada pada variabel ini."])
                ->withInput();
        }

        SpkFuzzySet::create($validated);
        MamdaniEngine::clearCache($variable->profile_id);

        return redirect()->route('settings.fuzzy.index', ['profile_id' => $variable->profile_id])
            ->with('success', 'Membership function berhasil ditambahkan.');
    }

    public function updateSet(Request $request, string $id)
    {
        $validated = $this->validateSetParams($request);
        $variable = SpkFuzzyVariable::findOrFail($validated['variable_id']);

        // Cek duplikat selain diri sendiri
        if (SpkFuzzySet::where('variable_id', $validated['variable_id'])->where('name', $validated['name'])->where('id', '!=', $id)->exists()) {
            return redirect()->route('settings.fuzzy.index', ['profile_id' => $variable->profile_id])
                ->withErrors(['name' => "Set '{$validated['name']}' sudah ada pada variabel ini."])
                ->withInput();
        }

        SpkFuzzySet::findOrFail($id)->update($validated);
        MamdaniEngine::clearCache($variable->profile_id);

        return redirect()->route('settings.fuzzy.index', ['profile_id' => $variable->profile_id])
            ->with('success', 'Membership function berhasil diperbarui.');
    }

    public function destroySet(string $id)
    {
        $set = SpkFuzzySet::findOrFail($id);

        // Cek apakah set sedang digunakan oleh rule
        $ruleCount = SpkFuzzyRule::where('output_set_id', $id)->count();
        $condCount = SpkFuzzyRuleCondition::where('set_id', $id)->count();

        if ($ruleCount > 0 || $condCount > 0) {
            return redirect()->route('settings.fuzzy.index', ['profile_id' => $set->variable?->profile_id])
                ->withErrors(['delete' => "Set '{$set->name}' masih digunakan oleh {$ruleCount} rule dan {$condCount} kondisi. Hapus rule terkait terlebih dahulu."]);
        }

        $profileId = $set->variable?->profile_id;
        $set->delete();
        MamdaniEngine::clearCache($profileId);

        return redirect()->route('settings.fuzzy.index', ['profile_id' => $profileId])
            ->with('success', 'Membership function berhasil dihapus.');
    }

    // ── RULES ───────────────────────────────────────────────────

    public function storeRule(Request $request)
    {
        $validated = $request->validate([
            'profile_id'    => 'required|exists:spk_fuzzy_profiles,id',
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
            return redirect()->route('settings.fuzzy.index', ['profile_id' => $validated['profile_id'], 'tab' => 'rules'])
                ->withErrors(['conditions' => 'Tidak boleh ada variabel yang duplikat dalam satu rule.'])
                ->withInput();
        }

        if (! $this->ruleBelongsToProfile($validated['profile_id'], $validated['output_set_id'], $validated['conditions'])) {
            return redirect()->route('settings.fuzzy.index', ['profile_id' => $validated['profile_id'], 'tab' => 'rules'])
                ->withErrors(['conditions' => 'Output dan kondisi rule harus berasal dari profile fuzzy yang sama.'])
                ->withInput();
        }

        DB::transaction(function () use ($validated) {
            $ruleCount = SpkFuzzyRule::where('profile_id', $validated['profile_id'])->where('group', $validated['group'])->count();
            $rule = SpkFuzzyRule::create([
                'profile_id'    => $validated['profile_id'],
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
        MamdaniEngine::clearCache($validated['profile_id']);

        return redirect()->route('settings.fuzzy.index', ['profile_id' => $validated['profile_id'], 'tab' => 'rules'])
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
            $rule = SpkFuzzyRule::find($id);
            return redirect()->route('settings.fuzzy.index', ['profile_id' => $rule?->profile_id, 'tab' => 'rules'])
                ->withErrors(['conditions' => 'Tidak boleh ada variabel yang duplikat dalam satu rule.'])
                ->withInput();
        }

        $profileId = SpkFuzzyRule::where('id', $id)->value('profile_id');
        if (! $this->ruleBelongsToProfile($profileId, $validated['output_set_id'], $validated['conditions'])) {
            return redirect()->route('settings.fuzzy.index', ['profile_id' => $profileId, 'tab' => 'rules'])
                ->withErrors(['conditions' => 'Output dan kondisi rule harus berasal dari profile fuzzy yang sama.'])
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
        MamdaniEngine::clearCache($profileId);

        return redirect()->route('settings.fuzzy.index', ['profile_id' => $profileId, 'tab' => 'rules'])
            ->with('success', 'Rule berhasil diperbarui.');
    }

    public function destroyRule(string $id)
    {
        $profileId = SpkFuzzyRule::where('id', $id)->value('profile_id');
        SpkFuzzyRuleCondition::where('rule_id', $id)->delete();
        SpkFuzzyRule::findOrFail($id)->delete();
        MamdaniEngine::clearCache($profileId);

        return redirect()->route('settings.fuzzy.index', ['profile_id' => $profileId, 'tab' => 'rules'])
            ->with('success', 'Rule berhasil dihapus.');
    }

    public function storeSource(Request $request)
    {
        [$validated, $payload] = $this->validateAndBuildSourcePayload($request);

        $existing = SpkFuzzyInputSource::where('variable_id', $validated['variable_id'])->first();
        if ($existing) {
            $existing->update($payload);
            MamdaniEngine::clearCache($payload['profile_id']);

            return redirect()->route('settings.fuzzy.index', ['profile_id' => $payload['profile_id'], 'tab' => 'sources'])
                ->with('success', 'Sumber data input berhasil diperbarui.');
        }

        SpkFuzzyInputSource::create($payload);
        MamdaniEngine::clearCache($payload['profile_id']);

        return redirect()->route('settings.fuzzy.index', ['profile_id' => $payload['profile_id'], 'tab' => 'sources'])
            ->with('success', 'Sumber data input berhasil ditambahkan.');
    }

    public function updateSource(Request $request, string $id)
    {
        $source = SpkFuzzyInputSource::findOrFail($id);
        [$validated, $payload] = $this->validateAndBuildSourcePayload($request, $source);

        $source->update($payload);
        MamdaniEngine::clearCache($payload['profile_id']);

        return redirect()->route('settings.fuzzy.index', ['profile_id' => $payload['profile_id'], 'tab' => 'sources'])
            ->with('success', 'Sumber data input berhasil diperbarui.');
    }

    public function destroySource(string $id)
    {
        $source = SpkFuzzyInputSource::findOrFail($id);
        $profileId = $source->profile_id ?: $source->variable?->profile_id;
        $source->delete();
        MamdaniEngine::clearCache($profileId);

        return redirect()->route('settings.fuzzy.index', ['profile_id' => $profileId, 'tab' => 'sources'])
            ->with('success', 'Sumber data input berhasil dihapus.');
    }

    private function validateAndBuildSourcePayload(Request $request, ?SpkFuzzyInputSource $existing = null): array
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:spk_fuzzy_profiles,id',
            'variable_id' => 'required|exists:spk_fuzzy_variables,id',
            'source_type' => 'required|in:iot,report_metric,function,database',
            'parameter_code' => 'nullable|string|max:50',
            'metric_code' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/'],
            'function_name' => 'nullable|string|max:200',
            'source_name' => 'nullable|string|max:150',
            'field_name' => 'nullable|string|max:100',
            'aggregation' => 'nullable|in:sum,avg,average,latest,count',
            'date_scope' => 'nullable|in:today,week,month,all',
            'max_age_minutes' => 'nullable|integer|min:1|max:10080',
            'offline_after_misses' => 'nullable|integer|min:1|max:20',
        ], [
            'metric_code.regex' => 'Kode metric hanya boleh huruf, angka, dan underscore.',
        ]);

        $variable = SpkFuzzyVariable::where('id', $validated['variable_id'])
            ->where('profile_id', $validated['profile_id'])
            ->where('type', 'input')
            ->first();
        $profile = SpkFuzzyProfile::find($validated['profile_id']);

        if (! $variable || $variable->group === 'kausalitas') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'variable_id' => 'Sumber data hanya bisa dipasang ke variabel input non-kausalitas pada profile yang sama.',
            ]);
        }

        if ($existing && $existing->variable_id !== $validated['variable_id']) {
            $duplicate = SpkFuzzyInputSource::where('variable_id', $validated['variable_id'])
                ->where('id', '!=', $existing->id)
                ->exists();

            if ($duplicate) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'variable_id' => 'Variabel tersebut sudah memiliki sumber data.',
                ]);
            }
        }

        $extra = [];
        $payload = [
            'profile_id' => $validated['profile_id'],
            'variable_id' => $validated['variable_id'],
            'source_type' => $validated['source_type'],
            'source_name' => null,
            'field_name' => null,
            'function_name' => null,
            'extra_config' => null,
        ];

        if ($validated['source_type'] === 'iot') {
            if (empty($validated['parameter_code'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['parameter_code' => 'Parameter IoT wajib dipilih.']);
            }

            $exists = IotParameter::where('parameterCode', $validated['parameter_code'])->exists();
            if (! $exists) {
                throw \Illuminate\Validation\ValidationException::withMessages(['parameter_code' => 'Parameter IoT tidak ditemukan.']);
            }

            $allowedCodes = $this->livestockMasterConfigService
                ->configuredIotParametersForJenis(
                    $profile?->jenis_budidaya_id
                        ?: SpkFuzzyProfile::resolveJenisBudidayaIdFromCommodity($profile?->commodity_id),
                    false
                )
                ->pluck('parameterCode')
                ->values()
                ->all();

            if ($this->livestockMasterConfigService->hasSchema() && ! in_array($validated['parameter_code'], $allowedCodes, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['parameter_code' => 'Parameter IoT belum masuk Data Master untuk jenis ternak ini.']);
            }

            $extra = [
                'parameterCode' => $validated['parameter_code'],
                'maxAgeMinutes' => (int) ($validated['max_age_minutes'] ?? 30),
                'offlineAfterMisses' => (int) ($validated['offline_after_misses'] ?? 3),
            ];

            $payload['source_name'] = 'iot_sensor_data';
            $payload['field_name'] = 'value';
        }

        if ($validated['source_type'] === 'report_metric') {
            if (empty($validated['metric_code'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['metric_code' => 'Kode metric laporan wajib diisi.']);
            }

            $extra = [
                'metricCode' => strtolower($validated['metric_code']),
                'aggregation' => $validated['aggregation'] ?? 'sum',
                'dateScope' => $validated['date_scope'] ?? 'today',
            ];

            $payload['source_name'] = 'daily_report_metrics';
            $payload['field_name'] = 'value';
        }

        if ($validated['source_type'] === 'function') {
            $functions = array_keys($this->availableSourceFunctions($profile?->commodity_id));
            if (empty($validated['function_name']) || ! in_array($validated['function_name'], $functions, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['function_name' => 'Function source tidak valid.']);
            }

            $payload['function_name'] = $validated['function_name'];
        }

        if ($validated['source_type'] === 'database') {
            $allowed = $this->allowedDatabaseFields();
            $sourceName = $validated['source_name'] ?? '';
            $fieldName = $validated['field_name'] ?? '';

            if (! isset($allowed[$sourceName]) || ! in_array($fieldName, $allowed[$sourceName], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['source_name' => 'Sumber database tidak termasuk daftar aman.']);
            }

            $extra = [
                'aggregation' => $validated['aggregation'] ?? 'sum',
                'dateScope' => $validated['date_scope'] ?? 'today',
            ];

            $payload['source_name'] = $sourceName;
            $payload['field_name'] = $fieldName;
        }

        $payload['extra_config'] = empty($extra) ? null : $extra;

        return [$validated, $payload];
    }

    private function availableSourceFunctions(?string $commodityId = null): array
    {
        return $this->livestockMasterConfigService->availableProductivityFunctionMap($commodityId);
    }

    private function allowedDatabaseFields(): array
    {
        return [
            'harianTernak' => ['pakan'],
            'panen' => ['jumlah', 'berat'],
            'kematian' => ['id'],
            'laporan' => ['id'],
        ];
    }

    // ── RESET ───────────────────────────────────────────────────

    private function ruleBelongsToProfile(?string $profileId, string $outputSetId, array $conditions): bool
    {
        if (! $profileId) {
            return false;
        }

        $outputSetProfileId = SpkFuzzySet::query()
            ->join('spk_fuzzy_variables', 'spk_fuzzy_variables.id', '=', 'spk_fuzzy_sets.variable_id')
            ->where('spk_fuzzy_sets.id', $outputSetId)
            ->value('spk_fuzzy_variables.profile_id');

        if ($outputSetProfileId !== $profileId) {
            return false;
        }

        $variableIds = collect($conditions)->pluck('variable_id')->filter()->unique()->values();
        $setIds = collect($conditions)->pluck('set_id')->filter()->unique()->values();

        $validVariableCount = SpkFuzzyVariable::where('profile_id', $profileId)
            ->whereIn('id', $variableIds)
            ->count();

        if ($validVariableCount !== $variableIds->count()) {
            return false;
        }

        $validSetCount = SpkFuzzySet::query()
            ->join('spk_fuzzy_variables', 'spk_fuzzy_variables.id', '=', 'spk_fuzzy_sets.variable_id')
            ->where('spk_fuzzy_variables.profile_id', $profileId)
            ->whereIn('spk_fuzzy_sets.id', $setIds)
            ->count();

        return $validSetCount === $setIds->count();
    }

    /**
     * Reset seluruh konfigurasi fuzzy ke default (jalankan seeder).
     */
    public function resetToDefault()
    {
        $stats = app(LayerChickenFuzzyDefaultTemplateService::class)->reset();

        return redirect()->route('settings.fuzzy.index', array_filter([
            'profile_id' => $stats['profile_id'] ?? null,
            'jenis_budidaya_id' => $stats['jenis_budidaya_id'] ?? null,
            'tab' => 'rules',
        ]))->with('success', 'Konfigurasi fuzzy ayam petelur berhasil di-reset ke default validasi pakar.');
    }
}
