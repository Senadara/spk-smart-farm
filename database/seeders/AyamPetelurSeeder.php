<?php

namespace Database\Seeders;

use App\Services\Fuzzy\LayerChickenFuzzyTemplateDefinition;
use App\Services\LivestockMasterConfigService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AyamPetelurSeeder extends Seeder
{
    private const OWNER_ID = 'fc571afa-e66b-437b-8b15-dce68edee3f3';

    private const PETUGAS_ID = '6e84fcc8-b5c2-4c60-94ef-bb9b7af6005c';

    private const SATUAN_EKOR_ID = '55555555-5555-5555-5555-555555555555';

    public function run(): void
    {
        if (! $this->hasCoreSchema()) {
            $this->command?->warn('AyamPetelurSeeder dilewati karena tabel inti farm belum tersedia.');

            return;
        }

        $now = Carbon::now();
        [$jenisBudidayaId, $komoditasId] = $this->ensureLayerMasterData($now);
        $this->ensureLivestockMasterConfiguration($jenisBudidayaId, $komoditasId, $now);

        $gradeIds = $this->ensureEggGrades($now);
        $parameterIds = $this->ensureIotParameters($now);
        $connectionConfigId = $this->ensureIotConnection($now);

        $scenarios = $this->scenarios();
        $coopIds = $this->ensureCoops($scenarios, $jenisBudidayaId, $now);
        $this->cleanupDemoRows($coopIds);

        foreach ($scenarios as $scenario) {
            $coopId = $coopIds[$scenario['code']];
            $objects = $this->seedObjects($scenario, $coopId, $now);
            $deviceId = $this->seedIotDevice($scenario, $coopId, $connectionConfigId, $parameterIds, $now);
            $this->seedIotSensorData($scenario, $deviceId, $parameterIds, $now);
            $this->seedDailyReports($scenario, $coopId, $komoditasId, $gradeIds, $objects, $now);
            $this->seedHealthIndication($scenario, $coopId, $objects, $now);
        }

        $this->command?->info('AyamPetelurSeeder: 6 skenario kandang ayam petelur demo berhasil disiapkan secara deterministik.');
    }

    private function hasCoreSchema(): bool
    {
        return Schema::hasTable('jenisBudidaya')
            && Schema::hasTable('komoditas')
            && Schema::hasTable('unitBudidaya')
            && Schema::hasTable('laporan')
            && Schema::hasTable('harianTernak')
            && Schema::hasTable('panen');
    }

    private function ensureLayerMasterData(Carbon $now): array
    {
        DB::table('satuan')->updateOrInsert(
            ['id' => self::SATUAN_EKOR_ID],
            [
                'nama' => 'Ekor',
                'lambang' => 'ekor',
                'isDeleted' => 0,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]
        );

        $jenisBudidayaId = DB::table('jenisBudidaya')
            ->where('isDeleted', 0)
            ->where('tipe', 'hewan')
            ->where(function ($query) {
                $query->where('nama', 'Ayam Petelur')
                    ->orWhere('nama', 'like', '%Layer%');
            })
            ->value('id') ?: $this->stableUuid('jenis:ayam-petelur');

        $jenisPayload = [
            'id' => $jenisBudidayaId,
            'nama' => 'Ayam Petelur',
            'tipe' => 'hewan',
            'latin' => 'Gallus gallus domesticus',
            'status' => 1,
            'detail' => 'Ayam petelur fase grower sampai produksi untuk demo SPK produktivitas.',
            'gambar' => 'images/barn-placeholder.jpg',
            'periodePanen' => 1,
            'isDeleted' => 0,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        DB::table('jenisBudidaya')->updateOrInsert(['id' => $jenisBudidayaId], $jenisPayload);

        $komoditasId = DB::table('komoditas')
            ->where('jenisBudidayaId', $jenisBudidayaId)
            ->where('isDeleted', 0)
            ->where(function ($query) {
                $query->where('nama', 'Ayam Layer')
                    ->orWhere('nama', 'like', '%Petelur%');
            })
            ->value('id') ?: $this->stableUuid('komoditas:ayam-layer');

        DB::table('komoditas')->updateOrInsert(
            ['id' => $komoditasId],
            [
                'jenisBudidayaId' => $jenisBudidayaId,
                'satuanId' => self::SATUAN_EKOR_ID,
                'nama' => 'Ayam Layer',
                'gambar' => 'images/barn-placeholder.jpg',
                'jumlah' => null,
                'hapusObjek' => 0,
                'tipeKomoditas' => 'individu',
                'panenConfig' => json_encode([
                    'mode' => 'telur_individu',
                    'requiredFields' => ['jumlah_butir', 'berat_kg'],
                    'gradeMode' => 'optional_percentage',
                ]),
                'isDeleted' => 0,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]
        );

        return [(string) $jenisBudidayaId, (string) $komoditasId];
    }

    private function ensureLivestockMasterConfiguration(string $jenisBudidayaId, string $komoditasId, Carbon $now): void
    {
        $service = app(LivestockMasterConfigService::class);
        if (! $service->hasSchema()) {
            return;
        }

        $service->ensureDefaultFunctionCatalog();
        $functionRows = DB::table('livestock_productivity_functions')
            ->where('is_active', true)
            ->get(['id', 'code'])
            ->keyBy('code');

        $productivityRows = [];
        foreach ($this->productivityConfig() as $index => $row) {
            $function = $functionRows->get($row['code']);
            if (! $function) {
                continue;
            }

            $productivityRows[] = [
                'function_id' => $function->id,
                'is_active' => $row['is_active'],
                'required_for_fuzzy' => $row['required_for_fuzzy'],
                'aggregation_scope' => $row['aggregation_scope'],
                'target_min_value' => $row['target_min_value'],
                'target_max_value' => $row['target_max_value'],
                'sort_order' => $index,
            ];
        }

        $service->saveConfiguration([
            'jenis_budidaya_id' => $jenisBudidayaId,
            'commodity_id' => $komoditasId,
            'notes' => 'Konfigurasi demo ayam petelur: data operasional dan input fuzzy dipisahkan.',
            'configured_by' => self::OWNER_ID,
            'afkir_label' => 'Afkir layer',
            'afkir_target_weeks' => 80,
            'afkir_warning_weeks' => 8,
            'production_start_weeks' => 18,
            'peak_start_weeks' => 25,
            'peak_end_weeks' => 45,
            'production_decline_weeks' => 46,
            'environment_parameters' => LayerChickenFuzzyTemplateDefinition::masterEnvironmentRows(),
            'productivity_functions' => $productivityRows,
        ]);
    }

    private function productivityConfig(): array
    {
        return [
            ['code' => 'hdp', 'is_active' => true, 'required_for_fuzzy' => true, 'aggregation_scope' => 'today', 'target_min_value' => 85, 'target_max_value' => 95],
            ['code' => 'feed_intake', 'is_active' => true, 'required_for_fuzzy' => false, 'aggregation_scope' => 'today', 'target_min_value' => 100, 'target_max_value' => 130],
            ['code' => 'mortalitas', 'is_active' => true, 'required_for_fuzzy' => true, 'aggregation_scope' => 'week', 'target_min_value' => 0, 'target_max_value' => 1],
            ['code' => 'fcr', 'is_active' => true, 'required_for_fuzzy' => true, 'aggregation_scope' => 'today', 'target_min_value' => 1.85, 'target_max_value' => 2.55],
            ['code' => 'egg_mass', 'is_active' => true, 'required_for_fuzzy' => false, 'aggregation_scope' => 'today', 'target_min_value' => 30, 'target_max_value' => null],
            ['code' => 'avg_egg_weight', 'is_active' => true, 'required_for_fuzzy' => false, 'aggregation_scope' => 'today', 'target_min_value' => 53, 'target_max_value' => 73],
            ['code' => 'flock_age', 'is_active' => true, 'required_for_fuzzy' => false, 'aggregation_scope' => 'today', 'target_min_value' => 18, 'target_max_value' => 80],
            ['code' => 'hhep', 'is_active' => false, 'required_for_fuzzy' => false, 'aggregation_scope' => 'month', 'target_min_value' => null, 'target_max_value' => null],
        ];
    }

    private function ensureEggGrades(Carbon $now): array
    {
        if (! Schema::hasTable('grade')) {
            return [];
        }

        $grades = [
            'Grade A' => 'Telur utuh ukuran seragam, layak jual utama.',
            'Grade B' => 'Telur utuh ukuran sedang atau tampilan kurang seragam.',
            'Grade C' => 'Telur kecil atau kualitas jual rendah.',
            'Reject' => 'Telur retak, pecah, atau kotor yang tidak masuk kualitas jual utama.',
        ];

        $ids = [];
        foreach ($grades as $name => $description) {
            $id = DB::table('grade')->where('nama', $name)->value('id') ?: $this->stableUuid('grade:'.$name);
            DB::table('grade')->updateOrInsert(
                ['id' => $id],
                [
                    'nama' => $name,
                    'deskripsi' => $description,
                    'isDeleted' => 0,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]
            );
            $ids[$name] = (string) $id;
        }

        return $ids;
    }

    private function ensureIotParameters(Carbon $now): array
    {
        if (! Schema::hasTable('iot_parameter')) {
            return [];
        }

        $parameters = [
            'TEMP' => ['Suhu', 'C', 'Suhu udara dalam kandang.'],
            'HUMID' => ['Kelembapan', '%', 'Kelembapan relatif kandang.'],
            'AMMON' => ['Amonia', 'ppm', 'Kadar amonia udara kandang.'],
            'LIGHT' => ['Cahaya', 'lx', 'Intensitas cahaya kandang.'],
        ];

        $ids = [];
        foreach ($parameters as $code => [$name, $unit, $description]) {
            $id = DB::table('iot_parameter')->where('parameterCode', $code)->value('id') ?: $this->stableUuid('iot-param:'.$code);
            DB::table('iot_parameter')->updateOrInsert(
                ['id' => $id],
                [
                    'parameterCode' => $code,
                    'parameterName' => $name,
                    'unit' => $unit,
                    'description' => $description,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]
            );
            $ids[$code] = (string) $id;
        }

        return $ids;
    }

    private function ensureIotConnection(Carbon $now): ?string
    {
        if (! Schema::hasTable('iot_protocol') || ! Schema::hasTable('iot_connection_config')) {
            return null;
        }

        $protocolId = DB::table('iot_protocol')->where('protocolName', 'MQTT')->value('id') ?: $this->stableUuid('iot-protocol:mqtt');
        DB::table('iot_protocol')->updateOrInsert(
            ['id' => $protocolId],
            [
                'protocolName' => 'MQTT',
                'description' => 'Koneksi MQTT demo untuk multi-device kandang.',
                'createdAt' => $now,
                'updatedAt' => $now,
            ]
        );

        $connectionId = DB::table('iot_connection_config')
            ->where('mqttTopic', 'smartfarm/demo/+/telemetry')
            ->value('id') ?: $this->stableUuid('iot-connection:demo-mqtt');

        DB::table('iot_connection_config')->updateOrInsert(
            ['id' => $connectionId],
            [
                'protocolId' => $protocolId,
                'baseUrl' => null,
                'endpointPath' => null,
                'mqttBrokerUrl' => env('DEMO_MQTT_HOST', 'broker.hivemq.com'),
                'mqttPort' => (int) env('DEMO_MQTT_PORT', 1883),
                'mqttTopic' => 'smartfarm/demo/+/telemetry',
                'mqttClientId' => env('DEMO_MQTT_CLIENT_ID', 'smartfarm-demo-web'),
                'mqttUsername' => env('DEMO_MQTT_USERNAME'),
                'mqttPassword' => env('DEMO_MQTT_PASSWORD'),
                'mqttUseTls' => (bool) env('DEMO_MQTT_TLS', false),
                'mqttQos' => 0,
                'mqttKeepAlive' => 60,
                'authType' => null,
                'authKey' => null,
                'headers' => null,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]
        );

        return (string) $connectionId;
    }

    private function scenarios(): array
    {
        return [
            [
                'code' => 'A',
                'name' => 'Kandang Layer A - Optimal',
                'legacy_names' => ['Kandang Layer A'],
                'location' => 'Blok A - Sayap Timur',
                'age_weeks' => 32,
                'capacity' => 700,
                'initial_population' => 620,
                'area' => 42.0,
                'description' => 'Flock fase puncak produksi dengan lingkungan stabil dan FCR efisien.',
                'hdp' => fn (int $day) => 91 + (($day % 4) * 0.7),
                'fcr' => fn (int $day) => 2.05 + (($day % 3) * 0.03),
                'egg_weight' => fn (int $day) => 61 + ($day % 3),
                'mortality' => fn (int $daysAgo) => in_array($daysAgo, [17, 8], true) ? 1 : 0,
                'environment' => ['temp' => 25.2, 'humidity' => 62, 'ammonia' => 6, 'light' => 38],
                'grade_ratio' => ['Grade A' => 0.88, 'Grade B' => 0.09, 'Grade C' => 0.02, 'Reject' => 0.01],
                'skip_today_reports' => false,
                'individual_mode' => 'stable',
            ],
            [
                'code' => 'B',
                'name' => 'Kandang Layer B - Produksi Turun',
                'legacy_names' => ['Kandang Layer B'],
                'location' => 'Blok B - Sayap Barat',
                'age_weeks' => 39,
                'capacity' => 650,
                'initial_population' => 580,
                'area' => 40.0,
                'description' => 'Flock fase produksi yang mengalami penurunan HDP, FCR boros, dan perlu tindak lanjut SPK.',
                'hdp' => fn (int $day) => $day < 14 ? 86 - (($day % 4) * 0.8) : [14 => 68, 15 => 61, 16 => 56, 17 => 52, 18 => 49, 19 => 48, 20 => 47][$day],
                'fcr' => fn (int $day) => $day < 14 ? 2.25 + (($day % 2) * 0.08) : 2.75 + (($day - 14) * 0.04),
                'egg_weight' => fn (int $day) => $day < 14 ? 59 : 55,
                'mortality' => fn (int $daysAgo) => [5 => 2, 4 => 4, 3 => 5, 2 => 3, 1 => 3, 0 => 2][$daysAgo] ?? 0,
                'environment' => ['temp' => 25.8, 'humidity' => 64, 'ammonia' => 7, 'light' => 35],
                'grade_ratio' => ['Grade A' => 0.74, 'Grade B' => 0.16, 'Grade C' => 0.06, 'Reject' => 0.04],
                'skip_today_reports' => false,
                'individual_mode' => 'drop',
            ],
            [
                'code' => 'C',
                'name' => 'Kandang Layer C - Lingkungan Waspada',
                'legacy_names' => [],
                'location' => 'Blok C - Dekat Gudang',
                'age_weeks' => 29,
                'capacity' => 550,
                'initial_population' => 500,
                'area' => 34.0,
                'description' => 'Produksi masih berjalan, tetapi suhu dan amonia sering melewati ambang ideal.',
                'hdp' => fn (int $day) => 76 + (($day % 3) * 1.5),
                'fcr' => fn (int $day) => 2.42 + (($day % 4) * 0.04),
                'egg_weight' => fn (int $day) => 58 + ($day % 2),
                'mortality' => fn (int $daysAgo) => in_array($daysAgo, [9, 2], true) ? 1 : 0,
                'environment' => ['temp' => 29.5, 'humidity' => 73, 'ammonia' => 23, 'light' => 42],
                'grade_ratio' => ['Grade A' => 0.79, 'Grade B' => 0.13, 'Grade C' => 0.05, 'Reject' => 0.03],
                'skip_today_reports' => false,
                'individual_mode' => 'slight_drop',
            ],
            [
                'code' => 'D',
                'name' => 'Kandang Layer D - Menjelang Afkir',
                'legacy_names' => [],
                'location' => 'Blok D - Kandang Lama',
                'age_weeks' => 77,
                'capacity' => 520,
                'initial_population' => 455,
                'area' => 36.0,
                'description' => 'Flock sudah mendekati target afkir, produksi wajar menurun karena umur.',
                'hdp' => fn (int $day) => 62 - (($day % 5) * 0.9),
                'fcr' => fn (int $day) => 2.65 + (($day % 3) * 0.05),
                'egg_weight' => fn (int $day) => 63 + ($day % 2),
                'mortality' => fn (int $daysAgo) => in_array($daysAgo, [16, 7, 1], true) ? 1 : 0,
                'environment' => ['temp' => 26.4, 'humidity' => 66, 'ammonia' => 10, 'light' => 33],
                'grade_ratio' => ['Grade A' => 0.68, 'Grade B' => 0.20, 'Grade C' => 0.08, 'Reject' => 0.04],
                'skip_today_reports' => false,
                'individual_mode' => 'aging',
            ],
            [
                'code' => 'E',
                'name' => 'Kandang Layer E - Pre Layer',
                'legacy_names' => [],
                'location' => 'Blok E - Grower',
                'age_weeks' => 16,
                'capacity' => 420,
                'initial_population' => 390,
                'area' => 30.0,
                'description' => 'Ayam belum masuk usia produksi, laporan harian berisi pakan dan kondisi kandang tanpa panen telur.',
                'hdp' => fn (int $day) => 0,
                'fcr' => fn (int $day) => 0,
                'egg_weight' => fn (int $day) => 0,
                'mortality' => fn (int $daysAgo) => 0,
                'environment' => ['temp' => 24.5, 'humidity' => 61, 'ammonia' => 5, 'light' => 28],
                'grade_ratio' => ['Grade A' => 0, 'Grade B' => 0, 'Grade C' => 0, 'Reject' => 0],
                'skip_today_reports' => false,
                'individual_mode' => 'pre_layer',
            ],
            [
                'code' => 'F',
                'name' => 'Kandang Layer F - Belum Lapor Hari Ini',
                'legacy_names' => [],
                'location' => 'Blok F - Cadangan',
                'age_weeks' => 34,
                'capacity' => 450,
                'initial_population' => 410,
                'area' => 28.0,
                'description' => 'Flock normal secara historis, tetapi laporan hari ini sengaja kosong untuk demo UX data belum lengkap.',
                'hdp' => fn (int $day) => 84 + (($day % 5) * 1.2),
                'fcr' => fn (int $day) => 2.18 + (($day % 3) * 0.04),
                'egg_weight' => fn (int $day) => 60 + ($day % 2),
                'mortality' => fn (int $daysAgo) => in_array($daysAgo, [11], true) ? 1 : 0,
                'environment' => ['temp' => 25.0, 'humidity' => 63, 'ammonia' => 8, 'light' => 34],
                'grade_ratio' => ['Grade A' => 0.84, 'Grade B' => 0.11, 'Grade C' => 0.03, 'Reject' => 0.02],
                'skip_today_reports' => true,
                'individual_mode' => 'stable',
            ],
        ];
    }

    private function ensureCoops(array $scenarios, string $jenisBudidayaId, Carbon $now): array
    {
        $ids = [];

        foreach ($scenarios as $scenario) {
            $coopId = DB::table('unitBudidaya')
                ->whereIn('nama', array_merge([$scenario['name']], $scenario['legacy_names']))
                ->where('jenisBudidayaId', $jenisBudidayaId)
                ->value('id') ?: $this->stableUuid('unit:'.$scenario['code']);

            $currentPopulation = $scenario['initial_population'] - $this->totalMortality($scenario);
            $createdAt = $now->copy()->subWeeks($scenario['age_weeks'])->setTime(8, 0);

            DB::table('unitBudidaya')->updateOrInsert(
                ['id' => $coopId],
                [
                    'jenisBudidayaId' => $jenisBudidayaId,
                    'owner_id' => self::OWNER_ID,
                    'nama' => $scenario['name'],
                    'lokasi' => $scenario['location'],
                    'tipe' => 'kolektif',
                    'luas' => $scenario['area'],
                    'kapasitas' => $scenario['capacity'],
                    'jumlah' => max(0, $currentPopulation),
                    'umurMinggu' => $scenario['age_weeks'],
                    'status' => 1,
                    'deskripsi' => $scenario['description'],
                    'gambar' => 'images/barn-placeholder.jpg',
                    'isDeleted' => 0,
                    'createdAt' => $createdAt,
                    'updatedAt' => $now,
                ]
            );

            foreach ($scenario['legacy_names'] as $legacyName) {
                DB::table('unitBudidaya')
                    ->where('nama', $legacyName)
                    ->where('id', '<>', $coopId)
                    ->update(['status' => 0, 'isDeleted' => 1, 'updatedAt' => $now]);
            }

            $ids[$scenario['code']] = (string) $coopId;
        }

        return $ids;
    }

    private function cleanupDemoRows(array $coopIds): void
    {
        if (empty($coopIds)) {
            return;
        }

        $reportIds = DB::table('laporan')
            ->whereIn('unitBudidayaId', array_values($coopIds))
            ->pluck('id')
            ->all();

        if (! empty($reportIds)) {
            $panenIds = Schema::hasTable('panen')
                ? DB::table('panen')->whereIn('laporanId', $reportIds)->pluck('id')->all()
                : [];
            $panenKebunIds = Schema::hasTable('panenKebun')
                ? DB::table('panenKebun')->whereIn('laporanId', $reportIds)->pluck('id')->all()
                : [];

            $this->deleteWhereIn('panenRincianGrade', 'panenId', $panenIds);
            $this->deleteWhereIn('panenRincianGrade', 'panenKebunId', $panenKebunIds);
            $this->deleteWhereIn('detailPanen', 'panenId', $panenIds);
            $this->deleteWhereIn('penggunaanInventaris', 'laporanId', $reportIds);
            $this->deleteWhereIn('daily_report_metrics', 'laporan_id', $reportIds);

            foreach (['harianTernak', 'harianKebun', 'kematian', 'sakit', 'vitamin', 'hama', 'panen', 'panenKebun'] as $table) {
                $this->deleteWhereIn($table, 'laporanId', $reportIds);
            }

            $this->deleteWhereIn('laporan', 'id', $reportIds);
        }

        if (Schema::hasTable('health_indications')) {
            $indicationIds = DB::table('health_indications')
                ->whereIn('unitBudidayaId', array_values($coopIds))
                ->pluck('id')
                ->all();

            $this->deleteWhereIn('health_indication_objects', 'healthIndicationId', $indicationIds);
            $this->deleteWhereIn('health_indications', 'id', $indicationIds);
        }

        if (Schema::hasTable('iot_device')) {
            $deviceIds = DB::table('iot_device')->whereIn('unitBudidayaId', array_values($coopIds))->pluck('id')->all();
            $this->deleteWhereIn('iot_sensor_data', 'deviceId', $deviceIds);
        }
    }

    private function seedObjects(array $scenario, string $coopId, Carbon $now): array
    {
        if (! Schema::hasTable('objekBudidaya')) {
            return [];
        }

        $objects = [];
        $createdAt = $now->copy()->subWeeks($scenario['age_weeks'])->setTime(8, 0);
        $targetAfkirAt = $createdAt->copy()->addWeeks(80);

        for ($i = 1; $i <= 12; $i++) {
            $name = $scenario['code'].'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $id = $this->stableUuid("object:{$scenario['code']}:{$i}");

            DB::table('objekBudidaya')->updateOrInsert(
                ['id' => $id],
                [
                    'namaId' => $name,
                    'unitBudidayaId' => $coopId,
                    'status' => 1,
                    'deskripsi' => "Objek contoh {$name} untuk simulasi panen individu.",
                    'tanggalMasuk' => $createdAt->toDateString(),
                    'umurMasukMinggu' => 0,
                    'targetAfkirAt' => $targetAfkirAt->toDateString(),
                    'batchKode' => 'BATCH-LAYER-'.$scenario['code'].'-2026',
                    'isDeleted' => 0,
                    'createdAt' => $createdAt,
                    'updatedAt' => $now,
                ]
            );

            $objects[] = ['id' => $id, 'namaId' => $name, 'index' => $i];
        }

        return $objects;
    }

    private function seedIotDevice(array $scenario, string $coopId, ?string $connectionConfigId, array $parameterIds, Carbon $now): ?string
    {
        if (! $connectionConfigId || empty($parameterIds) || ! Schema::hasTable('iot_device')) {
            return null;
        }

        $deviceId = DB::table('iot_device')
            ->where('unitBudidayaId', $coopId)
            ->where('deviceCode', 'SF-LAYER-'.$scenario['code'])
            ->value('id') ?: $this->stableUuid('iot-device:'.$scenario['code']);

        DB::table('iot_device')->updateOrInsert(
            ['id' => $deviceId],
            [
                'unitBudidayaId' => $coopId,
                'connectionConfigId' => $connectionConfigId,
                'deviceCode' => 'SF-LAYER-'.$scenario['code'],
                'deviceName' => 'Sensor Kandang Layer '.$scenario['code'],
                'pollingInterval' => 600,
                'mqttTopic' => 'smartfarm/demo/layer-'.$scenario['code'].'/telemetry',
                'webhookToken' => $this->stableUuid('webhook:'.$scenario['code']),
                'status' => 'active',
                'installedAt' => $now->copy()->subWeeks(min(12, $scenario['age_weeks'])),
                'lastSeenAt' => $scenario['code'] === 'F' ? $now->copy()->subHours(8) : $now->copy()->subMinutes(5),
                'lastMissedAt' => $scenario['code'] === 'F' ? $now->copy()->subHours(2) : null,
                'missedCount' => $scenario['code'] === 'F' ? 3 : 0,
                'offlineAfterMisses' => 3,
                'offlineAfterMinutes' => 30,
                'createdAt' => $now->copy()->subWeeks(min(12, $scenario['age_weeks'])),
                'updatedAt' => $now,
            ]
        );

        if (Schema::hasTable('iot_parameter_mapping')) {
            foreach ($parameterIds as $code => $parameterId) {
                DB::table('iot_parameter_mapping')->updateOrInsert(
                    ['id' => $this->stableUuid("iot-map:{$scenario['code']}:{$code}")],
                    [
                        'deviceId' => $deviceId,
                        'parameterId' => $parameterId,
                        'payloadKey' => strtolower($code === 'AMMON' ? 'ammonia' : $code),
                        'createdAt' => $now,
                        'updatedAt' => $now,
                    ]
                );
            }
        }

        return (string) $deviceId;
    }

    private function seedIotSensorData(array $scenario, ?string $deviceId, array $parameterIds, Carbon $now): void
    {
        if (! $deviceId || empty($parameterIds) || ! Schema::hasTable('iot_sensor_data')) {
            return;
        }

        for ($hoursAgo = 72; $hoursAgo >= 0; $hoursAgo -= 2) {
            $timestamp = $now->copy()->subHours($hoursAgo)->second(0);
            $hourFactor = ($timestamp->hour >= 10 && $timestamp->hour <= 15) ? 1 : 0;
            $values = [
                'TEMP' => round($scenario['environment']['temp'] + ($hourFactor * 1.2) + (($hoursAgo % 6) * 0.08), 2),
                'HUMID' => round($scenario['environment']['humidity'] - ($hourFactor * 4) + (($hoursAgo % 5) * 0.5), 2),
                'AMMON' => round($scenario['environment']['ammonia'] + (($hoursAgo % 4) * 0.7), 2),
                'LIGHT' => $timestamp->hour >= 5 && $timestamp->hour <= 18
                    ? round($scenario['environment']['light'] + 8, 2)
                    : 8,
            ];

            foreach ($values as $code => $value) {
                DB::table('iot_sensor_data')->updateOrInsert(
                    ['id' => $this->stableUuid("iot-data:{$deviceId}:{$code}:{$timestamp->toDateTimeString()}")],
                    [
                        'deviceId' => $deviceId,
                        'parameterId' => $parameterIds[$code],
                        'value' => $value,
                        'sensorTimestamp' => $timestamp,
                        'isDeleted' => 0,
                        'createdAt' => $timestamp,
                        'updatedAt' => $timestamp,
                    ]
                );
            }
        }
    }

    private function seedDailyReports(array $scenario, string $coopId, string $komoditasId, array $gradeIds, array $objects, Carbon $now): void
    {
        $population = $scenario['initial_population'];

        for ($daysAgo = 20; $daysAgo >= 0; $daysAgo--) {
            if ($scenario['skip_today_reports'] && $daysAgo === 0) {
                continue;
            }

            $dayIndex = 20 - $daysAgo;
            $date = $now->copy()->subDays($daysAgo)->startOfDay();
            $mortality = max(0, (int) $scenario['mortality']($daysAgo));
            $population = max(0, $population - $mortality);

            $this->seedDailyFeedReport($scenario, $coopId, $population, $date, $dayIndex);
            if ($mortality > 0) {
                $this->seedMortalityReport($scenario, $coopId, $mortality, $date);
            }

            $hdp = max(0, (float) $scenario['hdp']($dayIndex));
            if ($hdp <= 0) {
                continue;
            }

            $eggCount = max(0, (int) round($population * ($hdp / 100)));
            $avgEggWeightGram = max(45, (float) $scenario['egg_weight']($dayIndex));
            $eggMassKg = round(($eggCount * $avgEggWeightGram) / 1000, 2);
            $this->seedHarvestReport($scenario, $coopId, $komoditasId, $gradeIds, $objects, $date, $dayIndex, $population, $eggCount, $eggMassKg);
        }

        if (in_array($scenario['code'], ['B', 'C'], true)) {
            $this->seedSickObservationReport($scenario, $coopId, $now);
        }
    }

    private function seedDailyFeedReport(array $scenario, string $coopId, int $population, Carbon $date, int $dayIndex): void
    {
        $hdp = (float) $scenario['hdp']($dayIndex);
        $avgEggWeightGram = (float) $scenario['egg_weight']($dayIndex);
        $eggCount = $hdp > 0 ? (int) round($population * ($hdp / 100)) : 0;
        $eggMassKg = $eggCount > 0 ? round(($eggCount * $avgEggWeightGram) / 1000, 2) : 0.0;
        $fcr = (float) $scenario['fcr']($dayIndex);
        $feedKg = $eggMassKg > 0
            ? round($eggMassKg * $fcr, 2)
            : round($population * 0.075, 2);

        $reportId = $this->stableUuid("report:daily:{$scenario['code']}:{$date->toDateString()}");
        DB::table('laporan')->updateOrInsert(
            ['id' => $reportId],
            [
                'unitBudidayaId' => $coopId,
                'objekBudidayaId' => null,
                'userId' => self::PETUGAS_ID,
                'judul' => 'Demo laporan harian '.$scenario['name'],
                'tipe' => 'harian',
                'gambar' => null,
                'catatan' => $eggMassKg > 0
                    ? "Pakan {$feedKg} kg. Estimasi FCR hari ini {$fcr}."
                    : "Pakan grower {$feedKg} kg. Kandang belum masuk fase produksi telur.",
                'isDeleted' => 0,
                'createdAt' => $date->copy()->setTime(7, 30),
                'updatedAt' => $date->copy()->setTime(7, 30),
            ]
        );

        DB::table('harianTernak')->updateOrInsert(
            ['id' => $this->stableUuid("harian-ternak:{$reportId}")],
            [
                'laporanId' => $reportId,
                'pakan' => $feedKg,
                'cekKandang' => 1,
                'isDeleted' => 0,
                'createdAt' => $date->copy()->setTime(7, 30),
                'updatedAt' => $date->copy()->setTime(7, 30),
            ]
        );
    }

    private function seedHarvestReport(array $scenario, string $coopId, string $komoditasId, array $gradeIds, array $objects, Carbon $date, int $dayIndex, int $population, int $eggCount, float $eggMassKg): void
    {
        $reportId = $this->stableUuid("report:harvest:{$scenario['code']}:{$date->toDateString()}");
        $panenId = $this->stableUuid("harvest:{$scenario['code']}:{$date->toDateString()}");

        DB::table('laporan')->updateOrInsert(
            ['id' => $reportId],
            [
                'unitBudidayaId' => $coopId,
                'objekBudidayaId' => null,
                'userId' => self::PETUGAS_ID,
                'judul' => 'Demo panen telur '.$scenario['name'],
                'tipe' => 'panen',
                'gambar' => null,
                'catatan' => "{$eggCount} butir, berat {$eggMassKg} kg dari populasi {$population} ekor.",
                'isDeleted' => 0,
                'createdAt' => $date->copy()->setTime(16, 0),
                'updatedAt' => $date->copy()->setTime(16, 0),
            ]
        );

        DB::table('panen')->updateOrInsert(
            ['id' => $panenId],
            [
                'laporanId' => $reportId,
                'komoditasId' => $komoditasId,
                'jumlah' => $eggCount,
                'berat' => $eggMassKg,
                'jumlahHewan' => $population,
                'isDeleted' => 0,
                'createdAt' => $date->copy()->setTime(16, 0),
                'updatedAt' => $date->copy()->setTime(16, 0),
            ]
        );

        $this->seedGradeRows($scenario, $panenId, $gradeIds, $eggCount, $eggMassKg, $date);
        $this->seedIndividualHarvestRows($scenario, $panenId, $objects, $dayIndex, $date);
    }

    private function seedGradeRows(array $scenario, string $panenId, array $gradeIds, int $eggCount, float $eggMassKg, Carbon $date): void
    {
        $allocatedCount = 0;
        $allocatedWeight = 0.0;
        $gradeNames = array_keys($scenario['grade_ratio']);
        foreach ($gradeNames as $index => $gradeName) {
            if (! isset($gradeIds[$gradeName])) {
                continue;
            }

            $isLast = $index === array_key_last($gradeNames);
            $count = $isLast ? max(0, $eggCount - $allocatedCount) : (int) round($eggCount * $scenario['grade_ratio'][$gradeName]);
            $weight = $isLast ? round(max(0, $eggMassKg - $allocatedWeight), 2) : round($eggMassKg * $scenario['grade_ratio'][$gradeName], 2);
            $allocatedCount += $count;
            $allocatedWeight += $weight;

            DB::table('panenRincianGrade')->updateOrInsert(
                ['id' => $this->stableUuid("grade-row:{$panenId}:{$gradeName}")],
                [
                    'panenId' => $panenId,
                    'panenKebunId' => null,
                    'gradeId' => $gradeIds[$gradeName],
                    'jumlah' => $count,
                    'berat' => $weight,
                    'persentaseJumlah' => $eggCount > 0 ? round(($count / $eggCount) * 100, 2) : 0,
                    'persentaseBerat' => $eggMassKg > 0 ? round(($weight / $eggMassKg) * 100, 2) : 0,
                    'isDeleted' => 0,
                    'createdAt' => $date->copy()->setTime(16, 0),
                    'updatedAt' => $date->copy()->setTime(16, 0),
                ]
            );
        }
    }

    private function seedIndividualHarvestRows(array $scenario, string $panenId, array $objects, int $dayIndex, Carbon $date): void
    {
        if (empty($objects) || ! Schema::hasTable('detailPanen')) {
            return;
        }

        $layingIndexes = match ($scenario['individual_mode']) {
            'drop' => $dayIndex < 14 ? range(1, 11) : [1, 2, 3, 4, 5, 6],
            'slight_drop' => $dayIndex % 4 === 0 ? range(1, 9) : range(1, 10),
            'aging' => $dayIndex % 3 === 0 ? range(1, 7) : range(1, 8),
            'pre_layer' => [],
            default => $dayIndex % 5 === 0 ? range(1, 10) : range(1, 11),
        };

        foreach ($objects as $object) {
            if (! in_array($object['index'], $layingIndexes, true)) {
                continue;
            }

            DB::table('detailPanen')->updateOrInsert(
                ['id' => $this->stableUuid("detail-panen:{$panenId}:{$object['id']}")],
                [
                    'panenId' => $panenId,
                    'objekBudidayaId' => $object['id'],
                    'isDeleted' => 0,
                    'createdAt' => $date->copy()->setTime(16, 0),
                    'updatedAt' => $date->copy()->setTime(16, 0),
                ]
            );
        }
    }

    private function seedMortalityReport(array $scenario, string $coopId, int $mortality, Carbon $date): void
    {
        $reportId = $this->stableUuid("report:mortality:{$scenario['code']}:{$date->toDateString()}");
        DB::table('laporan')->updateOrInsert(
            ['id' => $reportId],
            [
                'unitBudidayaId' => $coopId,
                'objekBudidayaId' => null,
                'userId' => self::PETUGAS_ID,
                'judul' => 'Demo laporan mortalitas '.$scenario['name'],
                'tipe' => 'kematian',
                'gambar' => null,
                'catatan' => "{$mortality} ekor mati. Mortalitas adalah ayam mati, bukan telur reject.",
                'isDeleted' => 0,
                'createdAt' => $date->copy()->setTime(9, 0),
                'updatedAt' => $date->copy()->setTime(9, 0),
            ]
        );

        for ($i = 1; $i <= $mortality; $i++) {
            DB::table('kematian')->updateOrInsert(
                ['id' => $this->stableUuid("mortality:{$reportId}:{$i}")],
                [
                    'laporanId' => $reportId,
                    'tanggal' => $date->copy()->setTime(9, 0),
                    'penyebab' => $scenario['code'] === 'B' ? 'Perlu observasi lanjutan, produksi telur ikut turun.' : 'Kematian sporadis dalam batas pemantauan.',
                    'isDeleted' => 0,
                    'createdAt' => $date->copy()->setTime(9, 0),
                    'updatedAt' => $date->copy()->setTime(9, 0),
                ]
            );
        }
    }

    private function seedSickObservationReport(array $scenario, string $coopId, Carbon $now): void
    {
        if (! Schema::hasTable('sakit')) {
            return;
        }

        $reportId = $this->stableUuid("report:sick-observation:{$scenario['code']}");
        $createdAt = $now->copy()->subDays($scenario['code'] === 'B' ? 2 : 1)->setTime(10, 0);

        DB::table('laporan')->updateOrInsert(
            ['id' => $reportId],
            [
                'unitBudidayaId' => $coopId,
                'objekBudidayaId' => null,
                'userId' => self::PETUGAS_ID,
                'judul' => 'Demo observasi kesehatan '.$scenario['name'],
                'tipe' => 'sakit',
                'gambar' => null,
                'catatan' => $scenario['code'] === 'B'
                    ? 'Beberapa ayam terlihat lesu, nafsu makan perlu dipantau dari sisa pakan.'
                    : 'Ayam panting pada siang hari, cek ventilasi dan kualitas udara.',
                'isDeleted' => 0,
                'createdAt' => $createdAt,
                'updatedAt' => $createdAt,
            ]
        );

        DB::table('sakit')->updateOrInsert(
            ['id' => $this->stableUuid("sick-observation:{$scenario['code']}")],
            [
                'laporanId' => $reportId,
                'diagnosisPenyakit' => null,
                'status' => 'Pemantauan',
                'isDeleted' => 0,
                'createdAt' => $createdAt,
                'updatedAt' => $createdAt,
            ]
        );
    }

    private function seedHealthIndication(array $scenario, string $coopId, array $objects, Carbon $now): void
    {
        if ($scenario['code'] !== 'B' || ! Schema::hasTable('health_indications') || ! Schema::hasTable('health_indication_objects')) {
            return;
        }

        $periodEnd = $now->copy()->toDateString();
        $periodStart = $now->copy()->subDays(6)->toDateString();
        $indicationId = $this->stableUuid('health-indication:layer-b:drop-40');

        DB::table('health_indications')->updateOrInsert(
            ['id' => $indicationId],
            [
                'unitBudidayaId' => $coopId,
                'source' => 'spk-web',
                'indicationCode' => 'EGG_PRODUCTIVITY_DROP_40',
                'analysisMode' => 'individual_productivity_drop',
                'title' => 'Indikasi penurunan produksi individu',
                'message' => 'Sebagian ayam contoh di Kandang Layer B turun produktivitas lebih dari 40% dalam 7 hari terakhir.',
                'severity' => 'warning',
                'status' => 'pending',
                'periodStart' => $periodStart,
                'periodEnd' => $periodEnd,
                'periodDays' => 7,
                'thresholdPercent' => 40,
                'affectedObjectCount' => 5,
                'context' => json_encode([
                    'baselineDays' => 7,
                    'currentDays' => 7,
                    'expectedMobileAction' => 'Petugas membuka checklist pemeriksaan sakit untuk objek ayam yang tercantum.',
                ]),
                'notificationResult' => json_encode(['seeded' => true, 'sent' => false]),
                'detectedAt' => $now->copy()->subMinutes(20),
                'checkedAt' => null,
                'checkedBy' => null,
                'isDeleted' => 0,
                'createdAt' => $now->copy()->subMinutes(20),
                'updatedAt' => $now->copy()->subMinutes(20),
            ]
        );

        foreach (array_slice($objects, 6, 5) as $object) {
            DB::table('health_indication_objects')->updateOrInsert(
                ['id' => $this->stableUuid("health-indication-object:{$indicationId}:{$object['id']}")],
                [
                    'healthIndicationId' => $indicationId,
                    'objekBudidayaId' => $object['id'],
                    'namaId' => $object['namaId'],
                    'dropPercent' => 45 + ($object['index'] % 4) * 3,
                    'dropPoints' => 4,
                    'currentLayingPercent' => 42,
                    'previousLayingPercent' => 78,
                    'currentLayingDays' => 3,
                    'previousLayingDays' => 7,
                    'status' => 'pending',
                    'checkedAt' => null,
                    'isDeleted' => 0,
                    'createdAt' => $now->copy()->subMinutes(20),
                    'updatedAt' => $now->copy()->subMinutes(20),
                ]
            );
        }
    }

    private function totalMortality(array $scenario): int
    {
        $total = 0;
        for ($daysAgo = 20; $daysAgo >= 0; $daysAgo--) {
            $total += max(0, (int) $scenario['mortality']($daysAgo));
        }

        return $total;
    }

    private function deleteWhereIn(string $table, string $column, array $values): void
    {
        if (empty($values) || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, $values)->delete();
    }

    private function stableUuid(string $seed): string
    {
        $hash = md5($seed);

        return sprintf(
            '%s-%s-4%s-8%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }
}
