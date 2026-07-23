<?php

namespace App\Http\Controllers;

use App\Models\IotParameter;
use App\Models\ProductUnit;
use App\Models\SupplierProductCategory;
use App\Services\LivestockMasterConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DataMasterController extends Controller
{
    public function __construct(
        protected LivestockMasterConfigService $livestockMasterConfigService
    ) {}

    /**
     * Data Master ternak membaca jenis ternak dari mobile, lalu web menentukan
     * parameter lingkungan dan katalog fungsi produktivitas yang bisa dipilih di konfigurasi SPK.
     */
    public function index(Request $request)
    {
        $activeTab = in_array($request->query('tab'), ['sensor-parameters', 'livestock', 'stock-categories', 'product-units'], true)
            ? $request->query('tab')
            : 'livestock';
        $masterOverview = $this->livestockMasterConfigService->overview($request->query('jenis_budidaya_id'));
        $sensorParameters = $this->sensorParameters();
        $sensorParameterUsage = $this->sensorParameterUsage($sensorParameters->pluck('id')->filter()->values()->all());
        $stockCategories = $this->stockCategories();
        $productUnits = $this->productUnits();
        $dataMasterSources = [
            'sensor_parameters' => Schema::hasTable('iot_parameter') ? 'web' : 'missing',
            'stock_categories' => Schema::hasTable('supplier_product_categories') ? 'web' : (Schema::hasTable('kategoriInventaris') ? 'mobile' : 'default'),
            'product_units' => Schema::hasTable('product_units') ? 'web' : (Schema::hasTable('satuan') ? 'mobile' : 'default'),
        ];
        $users = $this->getDummyUsers();
        $blokKebun = $this->getDummyBlokKebun();

        $roleOptions = [
            'pjawab' => 'Penanggung Jawab',
            'inventor' => 'Pengelola RFC',
            'petugas' => 'Petugas Perkebunan',
            'admin' => 'Administrator',
        ];
        $jenisBudidayaOptions = $this->getDummyJenisBudidaya();

        return view('data-master.index', compact(
            'activeTab',
            'masterOverview',
            'sensorParameters',
            'sensorParameterUsage',
            'stockCategories',
            'productUnits',
            'dataMasterSources',
            'users',
            'blokKebun',
            'roleOptions',
            'jenisBudidayaOptions'
        ));
    }

    public function storeLivestockMaster(Request $request)
    {
        if (! $this->livestockMasterConfigService->hasSchema()) {
            throw ValidationException::withMessages([
                'data_master' => 'Tabel Data Master ternak belum tersedia. Jalankan migration terbaru terlebih dahulu.',
            ]);
        }

        if (! Schema::hasTable('iot_parameter')) {
            throw ValidationException::withMessages([
                'sensor_parameters' => 'Tabel parameter sensor belum tersedia. Jalankan migration terbaru terlebih dahulu.',
            ]);
        }

        $request->merge([
            'environment_parameters' => collect($request->input('environment_parameters', []))
                ->map(function ($row) {
                    $row = (array) $row;
                    $row['parameter_code'] = $this->normalizeSensorCode($row['parameter_code'] ?? '');

                    return $row;
                })
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'jenis_budidaya_id' => 'required|string|exists:jenisBudidaya,id',
            'commodity_id' => 'nullable|string|exists:komoditas,id',
            'notes' => 'nullable|string|max:1000',
            'environment_parameters' => 'required|array|min:1',
            'environment_parameters.*.parameter_code' => ['nullable', 'string', 'max:50', 'exists:iot_parameter,parameterCode'],
            'environment_parameters.*.parameter_name' => 'nullable|string|max:150',
            'environment_parameters.*.unit' => 'nullable|string|max:30',
            'environment_parameters.*.icon_key' => 'nullable|string|in:sensor,gauge,air,water,light,alert',
            'environment_parameters.*.min_value' => 'nullable|numeric',
            'environment_parameters.*.max_value' => 'nullable|numeric',
            'environment_parameters.*.fallback_value' => 'nullable|numeric',
            'environment_parameters.*.stale_minutes' => 'nullable|integer|min:1|max:10080',
            'environment_parameters.*.required_for_iot' => 'nullable|boolean',
            'environment_parameters.*.required_for_fuzzy' => 'nullable|boolean',
            'productivity_function_ids' => 'nullable|array',
            'productivity_function_ids.*' => 'string|exists:livestock_productivity_functions,id',
            'productivity_functions' => 'nullable|array',
            'productivity_functions.*.function_id' => 'required_with:productivity_functions|string|exists:livestock_productivity_functions,id',
            'productivity_functions.*.is_active' => 'nullable|boolean',
            'productivity_functions.*.required_for_fuzzy' => 'nullable|boolean',
            'productivity_functions.*.aggregation_scope' => 'nullable|string|in:today,week,month',
        ], [
            'environment_parameters.required' => 'Minimal satu parameter lingkungan wajib diisi.',
            'environment_parameters.min' => 'Minimal satu parameter lingkungan wajib diisi.',
            'environment_parameters.*.parameter_code.exists' => 'Kode sensor harus dipilih dari Data Master Parameter Sensor.',
            'productivity_functions.*.function_id.exists' => 'Fungsi produktivitas tidak ditemukan di katalog.',
        ]);

        $filledEnvironmentRows = collect($validated['environment_parameters'])
            ->filter(fn ($row) => trim((string) ($row['parameter_code'] ?? '')) !== '' || trim((string) ($row['parameter_name'] ?? '')) !== '');

        if ($filledEnvironmentRows->isEmpty()) {
            throw ValidationException::withMessages([
                'environment_parameters' => 'Minimal satu parameter lingkungan wajib diisi.',
            ]);
        }

        foreach ($filledEnvironmentRows as $index => $row) {
            if (trim((string) ($row['parameter_code'] ?? '')) === '' || trim((string) ($row['parameter_name'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "environment_parameters.{$index}.parameter_code" => 'Kode dan nama parameter wajib diisi pada baris yang aktif.',
                ]);
            }

            if (($row['min_value'] ?? null) !== null && ($row['max_value'] ?? null) !== null && (float) $row['min_value'] >= (float) $row['max_value']) {
                throw ValidationException::withMessages([
                    "environment_parameters.{$index}.min_value" => 'Nilai minimum harus lebih kecil dari maksimum.',
                ]);
            }
        }

        $validated['environment_parameters'] = $this->hydrateEnvironmentRowsFromSensorCatalog($filledEnvironmentRows->values()->all());
        $validated['productivity_functions'] = collect($validated['productivity_functions'] ?? [])
            ->map(fn ($row) => [
                'function_id' => $row['function_id'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? false),
                'required_for_fuzzy' => (bool) ($row['required_for_fuzzy'] ?? false),
                'aggregation_scope' => $row['aggregation_scope'] ?? 'today',
            ])
            ->filter(fn ($row) => ! empty($row['function_id']))
            ->values()
            ->all();
        $validated['configured_by'] = data_get(session('user'), 'id');

        $this->livestockMasterConfigService->saveConfiguration($validated);

        return redirect()
            ->route('data-master.index', ['jenis_budidaya_id' => $validated['jenis_budidaya_id']])
            ->with('success', 'Konfigurasi Data Master ternak berhasil disimpan.');
    }

    public function storeSensorParameter(Request $request)
    {
        $this->ensureTableReady('iot_parameter', 'Tabel parameter sensor belum tersedia.');

        $request->merge([
            'parameterCode' => $this->normalizeSensorCode($request->input('parameterCode')),
        ]);

        $validated = $request->validate([
            'parameterCode' => 'required|string|max:50|unique:iot_parameter,parameterCode',
            'parameterName' => 'required|string|max:100',
            'unit' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
        ], [
            'parameterCode.required' => 'Kode sensor wajib diisi.',
            'parameterCode.unique' => 'Kode sensor sudah terdaftar.',
        ]);

        IotParameter::create($validated);

        return redirect()
            ->route('data-master.index', ['tab' => 'sensor-parameters'])
            ->with('success', 'Parameter sensor berhasil ditambahkan.');
    }

    public function updateSensorParameter(Request $request, IotParameter $parameter)
    {
        $request->merge([
            'parameterCode' => $this->normalizeSensorCode($request->input('parameterCode')),
        ]);

        $validated = $request->validate([
            'parameterCode' => 'required|string|max:50|unique:iot_parameter,parameterCode,'.$parameter->id,
            'parameterName' => 'required|string|max:100',
            'unit' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
        ], [
            'parameterCode.required' => 'Kode sensor wajib diisi.',
            'parameterCode.unique' => 'Kode sensor sudah terdaftar.',
        ]);

        $parameter->update($validated);

        return redirect()
            ->route('data-master.index', ['tab' => 'sensor-parameters'])
            ->with('success', 'Parameter sensor berhasil diperbarui.');
    }

    public function destroySensorParameter(IotParameter $parameter)
    {
        $usageCount = $this->sensorParameterUsageCount($parameter->id);

        if ($usageCount > 0) {
            throw ValidationException::withMessages([
                'sensor_parameter' => "Parameter {$parameter->parameterCode} masih dipakai di {$usageCount} konfigurasi/data. Hapus atau pindahkan referensinya terlebih dahulu.",
            ]);
        }

        $parameter->delete();

        return redirect()
            ->route('data-master.index', ['tab' => 'sensor-parameters'])
            ->with('success', 'Parameter sensor berhasil dihapus.');
    }

    public function storeStockCategory(Request $request)
    {
        $this->ensureTableReady('supplier_product_categories', 'Tabel kategori stok belum tersedia.');

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:255',
        ]);

        SupplierProductCategory::query()->updateOrCreate(
            ['slug' => Str::slug($validated['name'])],
            [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'sort_order' => (int) SupplierProductCategory::query()->max('sort_order') + 1,
                'is_active' => true,
            ]
        );

        return redirect()
            ->route('data-master.index', ['tab' => 'stock-categories'])
            ->with('success', 'Kategori stok berhasil disimpan.');
    }

    public function updateStockCategory(Request $request, SupplierProductCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('data-master.index', ['tab' => 'stock-categories'])
            ->with('success', 'Kategori stok berhasil diperbarui.');
    }

    public function storeProductUnit(Request $request)
    {
        $this->ensureTableReady('product_units', 'Tabel satuan produk belum tersedia.');

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'symbol' => 'required|string|max:30',
            'description' => 'nullable|string|max:255',
        ]);

        ProductUnit::query()->updateOrCreate(
            ['symbol' => $validated['symbol']],
            [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'sort_order' => (int) ProductUnit::query()->max('sort_order') + 1,
                'is_active' => true,
            ]
        );

        return redirect()
            ->route('data-master.index', ['tab' => 'product-units'])
            ->with('success', 'Satuan produk berhasil disimpan.');
    }

    public function updateProductUnit(Request $request, ProductUnit $unit)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'symbol' => 'required|string|max:30',
            'description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $unit->update([
            'name' => $validated['name'],
            'symbol' => $validated['symbol'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('data-master.index', ['tab' => 'product-units'])
            ->with('success', 'Satuan produk berhasil diperbarui.');
    }

    private function sensorParameters(): Collection
    {
        if (! Schema::hasTable('iot_parameter')) {
            return collect();
        }

        return IotParameter::query()
            ->orderBy('parameterCode')
            ->get()
            ->map(function ($row) {
                $row->source = 'web';
                $row->editable = true;

                return $row;
            });
    }

    private function sensorParameterUsage(array $parameterIds): array
    {
        if (empty($parameterIds)) {
            return [];
        }

        return collect($parameterIds)
            ->mapWithKeys(fn ($id) => [$id => $this->sensorParameterUsageCount((string) $id)])
            ->all();
    }

    private function sensorParameterUsageCount(string $parameterId): int
    {
        $usageTables = [
            ['table' => 'iot_parameter_mapping', 'column' => 'parameterId'],
            ['table' => 'commodity_parameter', 'column' => 'parameterId'],
            ['table' => 'livestock_environment_parameters', 'column' => 'parameter_id'],
            ['table' => 'iot_sensor_data', 'column' => 'parameterId'],
        ];

        return collect($usageTables)->sum(function (array $config) use ($parameterId) {
            if (! Schema::hasTable($config['table']) || ! Schema::hasColumn($config['table'], $config['column'])) {
                return 0;
            }

            return DB::table($config['table'])
                ->where($config['column'], $parameterId)
                ->count();
        });
    }

    private function hydrateEnvironmentRowsFromSensorCatalog(array $rows): array
    {
        $codes = collect($rows)
            ->pluck('parameter_code')
            ->map(fn ($code) => $this->normalizeSensorCode($code))
            ->filter()
            ->unique()
            ->values();

        $catalog = IotParameter::query()
            ->whereIn('parameterCode', $codes->all())
            ->get()
            ->keyBy('parameterCode');

        return collect($rows)
            ->map(function (array $row) use ($catalog) {
                $code = $this->normalizeSensorCode($row['parameter_code'] ?? '');
                $parameter = $catalog->get($code);

                if ($parameter) {
                    $row['parameter_code'] = $parameter->parameterCode;
                    $row['parameter_name'] = $parameter->parameterName;
                    $row['unit'] = $parameter->unit;
                }

                return $row;
            })
            ->values()
            ->all();
    }

    private function normalizeSensorCode(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/[^A-Z0-9_]+/', '_', $value) ?: '';
        $value = preg_replace('/_+/', '_', $value) ?: '';

        return trim($value, '_');
    }

    private function ensureTableReady(string $table, string $message): void
    {
        if (! Schema::hasTable($table)) {
            throw ValidationException::withMessages([
                'data_master' => $message.' Jalankan migration terbaru terlebih dahulu.',
            ]);
        }
    }

    private function stockCategories(): Collection
    {
        if (Schema::hasTable('supplier_product_categories')) {
            return SupplierProductCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function ($row) {
                    $row->source = 'web';
                    $row->editable = true;

                    return $row;
                });
        }

        if (Schema::hasTable('kategoriInventaris')) {
            $hasDeletedFlag = Schema::hasColumn('kategoriInventaris', 'isDeleted');
            $query = DB::table('kategoriInventaris')
                ->select('id', 'nama')
                ->orderBy('nama');

            if ($hasDeletedFlag) {
                $query->addSelect('isDeleted');
                $query->where('isDeleted', false);
            }

            return $query->get()
                ->map(fn ($row) => (object) [
                    'id' => $row->id,
                    'name' => $row->nama,
                    'slug' => Str::slug($row->nama),
                    'description' => 'Dibaca dari Data Master mobile.',
                    'sort_order' => 0,
                    'is_active' => ! (bool) ($row->isDeleted ?? false),
                    'source' => 'mobile',
                    'editable' => false,
                ]);
        }

        return collect(SupplierProductCategory::defaultRows())
            ->map(fn ($row, $index) => (object) [
                'id' => null,
                'name' => $row['name'],
                'slug' => Str::slug($row['name']),
                'description' => $row['description'],
                'sort_order' => $index + 1,
                'is_active' => true,
                'source' => 'default',
                'editable' => false,
            ]);
    }

    private function productUnits(): Collection
    {
        if (Schema::hasTable('product_units')) {
            return ProductUnit::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function ($row) {
                    $row->source = 'web';
                    $row->editable = true;

                    return $row;
                });
        }

        if (Schema::hasTable('satuan')) {
            $hasDeletedFlag = Schema::hasColumn('satuan', 'isDeleted');
            $query = DB::table('satuan')
                ->select('id', 'nama', 'lambang')
                ->orderBy('nama');

            if ($hasDeletedFlag) {
                $query->addSelect('isDeleted');
                $query->where('isDeleted', false);
            }

            return $query->get()
                ->map(fn ($row) => (object) [
                    'id' => $row->id,
                    'name' => $row->nama,
                    'symbol' => $row->lambang,
                    'description' => 'Dibaca dari Data Master mobile.',
                    'sort_order' => 0,
                    'is_active' => ! (bool) ($row->isDeleted ?? false),
                    'source' => 'mobile',
                    'editable' => false,
                ]);
        }

        return collect(ProductUnit::defaultRows())
            ->map(fn ($row, $index) => (object) [
                'id' => null,
                'name' => $row['name'],
                'symbol' => $row['symbol'],
                'description' => $row['description'],
                'sort_order' => $index + 1,
                'is_active' => true,
                'source' => 'default',
                'editable' => false,
            ]);
    }

    /**
     * TODO: [DASH-02] Ganti dengan query Eloquent:
     * User::whereIn('role', ['pjawab', 'inventor', 'petugas', 'admin'])
     *     ->where('isDeleted', 0)
     *     ->select('id', 'nama', 'email', 'role', 'status', 'createdAt')
     *     ->orderBy('role')
     *     ->get();
     */
    private function getDummyUsers(): array
    {
        return [
            [
                'id' => 'usr-001',
                'nama' => 'Dr. Ahmad Suryadi',
                'email' => 'ahmad.suryadi@rfc.telkomuniversity.ac.id',
                'role' => 'pjawab',
                'status' => 1,
                'createdAt' => '2024-08-15 09:00:00',
            ],
            [
                'id' => 'usr-002',
                'nama' => 'Siti Nurhaliza',
                'email' => 'siti.nurhaliza@rfc.telkomuniversity.ac.id',
                'role' => 'inventor',
                'status' => 1,
                'createdAt' => '2024-08-15 09:30:00',
            ],
            [
                'id' => 'usr-003',
                'nama' => 'Budi Santoso',
                'email' => 'budi.santoso@rfc.telkomuniversity.ac.id',
                'role' => 'petugas',
                'status' => 1,
                'createdAt' => '2024-09-01 08:00:00',
            ],
            [
                'id' => 'usr-004',
                'nama' => 'Rina Wulandari',
                'email' => 'rina.wulandari@rfc.telkomuniversity.ac.id',
                'role' => 'petugas',
                'status' => 1,
                'createdAt' => '2024-09-01 08:15:00',
            ],
            [
                'id' => 'usr-005',
                'nama' => 'Hendro Prasetyo',
                'email' => 'hendro.prasetyo@rfc.telkomuniversity.ac.id',
                'role' => 'inventor',
                'status' => 0,
                'createdAt' => '2024-10-05 10:00:00',
            ],
            [
                'id' => 'usr-006',
                'nama' => 'Admin Sistem',
                'email' => 'admin@rfc.telkomuniversity.ac.id',
                'role' => 'admin',
                'status' => 1,
                'createdAt' => '2024-08-01 00:00:00',
            ],
        ];
    }

    /**
     * TODO: [DASH-02] Ganti dengan query Eloquent:
     * DB::table('unitBudidaya')
     *     ->join('jenisBudidaya', 'unitBudidaya.JenisBudidayaId', '=', 'jenisBudidaya.id')
     *     ->where('jenisBudidaya.tipe', 'tumbuhan')
     *     ->where('unitBudidaya.isDeleted', 0)
     *     ->where('jenisBudidaya.isDeleted', 0)
     *     ->select(
     *         'unitBudidaya.id',
     *         'unitBudidaya.nama',
     *         'unitBudidaya.lokasi',
     *         'unitBudidaya.luas',
     *         'unitBudidaya.jumlah as kapasitas',
     *         'unitBudidaya.status',
     *         'unitBudidaya.deskripsi',
     *         'jenisBudidaya.nama as jenisBudidaya',
     *         'jenisBudidaya.latin as namaLatin'
     *     )
     *     ->orderBy('unitBudidaya.nama')
     *     ->get()
     *     ->map(fn($item) => (array) $item)
     *     ->toArray();
     */
    private function getDummyBlokKebun(): array
    {
        return [
            [
                'id' => 'ub-001',
                'nama' => 'Greenhouse A',
                'lokasi' => 'Rooftop Gedung A - Lantai 5',
                'luas' => 120.5,
                'kapasitas' => 200,
                'status' => 1,
                'deskripsi' => 'Greenhouse utama untuk budidaya melon premium',
                'jenisBudidaya' => 'Melon',
                'namaLatin' => 'Cucumis melo L.',
            ],
            [
                'id' => 'ub-002',
                'nama' => 'Greenhouse B',
                'lokasi' => 'Rooftop Gedung A - Lantai 5',
                'luas' => 95.0,
                'kapasitas' => 150,
                'status' => 1,
                'deskripsi' => 'Greenhouse pendukung untuk varietas melon golden',
                'jenisBudidaya' => 'Melon',
                'namaLatin' => 'Cucumis melo L.',
            ],
            [
                'id' => 'ub-003',
                'nama' => 'Greenhouse C',
                'lokasi' => 'Rooftop Gedung B - Lantai 4',
                'luas' => 80.0,
                'kapasitas' => 120,
                'status' => 1,
                'deskripsi' => 'Greenhouse eksperimen untuk uji coba varietas baru',
                'jenisBudidaya' => 'Melon',
                'namaLatin' => 'Cucumis melo L.',
            ],
            [
                'id' => 'ub-004',
                'nama' => 'Greenhouse D',
                'lokasi' => 'Rooftop Gedung B - Lantai 4',
                'luas' => 60.0,
                'kapasitas' => 80,
                'status' => 0,
                'deskripsi' => 'Greenhouse sedang dalam masa renovasi',
                'jenisBudidaya' => 'Melon',
                'namaLatin' => 'Cucumis melo L.',
            ],
            [
                'id' => 'ub-005',
                'nama' => 'Plot Pakcoy Hidroponik',
                'lokasi' => 'Rooftop Gedung C - Lantai 3',
                'luas' => 45.0,
                'kapasitas' => 500,
                'status' => 1,
                'deskripsi' => 'Plot sayuran hidroponik pendamping',
                'jenisBudidaya' => 'Pakcoy',
                'namaLatin' => 'Brassica rapa subsp. chinensis',
            ],
        ];
    }

    /**
     * TODO: [DASH-02] Ganti dengan query Eloquent:
     * DB::table('jenisBudidaya')
     *     ->where('tipe', 'tumbuhan')
     *     ->where('isDeleted', 0)
     *     ->pluck('nama', 'id')
     *     ->toArray();
     */
    private function getDummyJenisBudidaya(): array
    {
        return [
            'jb-001' => 'Melon',
            'jb-002' => 'Pakcoy',
        ];
    }
}
