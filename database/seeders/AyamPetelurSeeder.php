<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AyamPetelurSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 0. Ensure User
        $user = DB::table('user')->first();
        if (!$user) {
            $userId = Str::uuid()->toString();
            DB::table('user')->insert([
                'id' => $userId,
                'name' => 'System Seeder',
                'email' => 'seeder@example.com',
                'password' => bcrypt('password'),
                'createdAt' => now(),
                'updatedAt' => now(),
            ]);
        } else {
            $userId = $user->id;
        }

        // 1. Ensure Jenis Budidaya
        $jenisBudidaya = DB::table('jenisBudidaya')->where('nama', 'Ayam Petelur')->first();
        if (!$jenisBudidaya) {
            $jenisBudidayaId = Str::uuid()->toString();
            DB::table('jenisBudidaya')->insert([
                'id' => $jenisBudidayaId,
                'nama' => 'Ayam Petelur',
                'createdAt' => now(),
                'updatedAt' => now(),
                'updatedBy' => 'Seeder',
                'isDeleted' => 0,
            ]);
        } else {
            $jenisBudidayaId = $jenisBudidaya->id;
        }

        // 2. Ensure Grades
        $grades = ['Grade A', 'Grade B', 'Grade C', 'Grade AA', 'Afkir'];
        $gradeIds = [];
        foreach ($grades as $g) {
            $grade = DB::table('grade')->where('nama', $g)->first();
            if (!$grade) {
                $id = Str::uuid()->toString();
                DB::table('grade')->insert(['id' => $id, 'nama' => $g, 'createdAt' => now(), 'updatedAt' => now()]);
                $gradeIds[$g] = $id;
            } else {
                $gradeIds[$g] = $grade->id;
            }
        }

        // 3. Ensure IoT Parameters
        $params = ['TEMP' => 'Suhu', 'HUMID' => 'Kelembapan', 'AMMON' => 'Amonia', 'LIGHT' => 'Cahaya'];
        $paramIds = [];
        foreach ($params as $code => $name) {
            $p = DB::table('iot_parameter')->where('parameterCode', $code)->first();
            if (!$p) {
                $id = Str::uuid()->toString();
                DB::table('iot_parameter')->insert([
                    'id' => $id,
                    'parameterCode' => $code,
                    'parameterName' => $name,
                    'unit' => $code === 'TEMP' ? '°C' : ($code === 'HUMID' ? '%' : ($code === 'AMMON' ? 'ppm' : 'lx')),
                    'createdAt' => now(),
                ]);
                $paramIds[$code] = $id;
            } else {
                $paramIds[$code] = $p->id;
            }
        }

        // 4. Ensure IoT Protocol
        $protocol = DB::table('iot_protocol')->where('protocolName', 'MQTT')->first();
        if (!$protocol) {
            $protocolId = Str::uuid()->toString();
            DB::table('iot_protocol')->insert([
                'id' => $protocolId,
                'protocolName' => 'MQTT',
                'description' => 'MQTT Protocol for Sensor',
                'createdAt' => now(),
            ]);
        } else {
            $protocolId = $protocol->id;
        }

        // 5. Ensure Connection Config
        $config = DB::table('iot_connection_config')->where('mqttBrokerUrl', 'broker.hivemq.com')->first();
        if (!$config) {
            $configId = Str::uuid()->toString();
            DB::table('iot_connection_config')->insert([
                'id' => $configId,
                'protocolId' => $protocolId,
                'mqttBrokerUrl' => 'broker.hivemq.com',
                'mqttTopic' => 'smartfarm/sensors',
                'createdAt' => now(),
            ]);
        } else {
            $configId = $config->id;
        }

        // 6. Kandang (Unit Budidaya)
        $kandangs = [
            ['nama' => 'Kandang Layer A', 'lokasi' => 'Blok Timur', 'populasi' => 1000, 'age_weeks' => 30],
            ['nama' => 'Kandang Layer B', 'lokasi' => 'Blok Barat', 'populasi' => 800, 'age_weeks' => 45],
        ];

        foreach ($kandangs as $k) {
            $coop = DB::table('unitBudidaya')->where('nama', $k['nama'])->where('jenisBudidayaId', $jenisBudidayaId)->first();
            
            if (!$coop) {
                $coopId = Str::uuid()->toString();
                DB::table('unitBudidaya')->insert([
                    'id' => $coopId,
                    'jenisBudidayaId' => $jenisBudidayaId,
                    'nama' => $k['nama'],
                    'jumlah' => $k['populasi'],
                    'kapasitas' => 3000,
                    'lokasi' => $k['lokasi'],
                    'gambar' => null,
                    'createdAt' => now()->subWeeks($k['age_weeks']),
                    'updatedAt' => now(),
                    'status' => 1,
                    'isDeleted' => 0,
                ]);
            } else {
                $coopId = $coop->id;
            }

            // Ensure Device
            $device = DB::table('iot_device')->where('unitBudidayaId', $coopId)->first();
            if (!$device) {
                $deviceId = Str::uuid()->toString();
                DB::table('iot_device')->insert([
                    'id' => $deviceId,
                    'connectionConfigId' => $configId,
                    'unitBudidayaId' => $coopId,
                    'deviceCode' => 'DEV_' . strtoupper(Str::random(6)),
                    'deviceName' => 'Sensor Nodes ' . $k['nama'],
                    'status' => 'active',
                    'createdAt' => now(),
                ]);
            } else {
                $deviceId = $device->id;
            }

            // Seed IoT Data for past 24 hours
            $recentData = DB::table('iot_sensor_data')->where('deviceId', $deviceId)->where('sensorTimestamp', '>=', now()->subHours(24))->count();
            
            if ($recentData < 24) { 
                $sensorInserts = [];
                for ($h = 24; $h >= 0; $h--) {
                    $ts = now()->subHours($h);
                    
                    $baseTemp = 24; 
                    if ($ts->hour > 8 && $ts->hour < 16) $baseTemp = 27; 
                    $temp = $baseTemp + (rand(-15, 15) / 10); 
                    
                    $baseHumid = 65; 
                    if ($baseTemp > 26) $baseHumid = 55;
                    $humid = $baseHumid + rand(-5, 5);

                    $ammon = rand(50, 150) / 10; 

                    $light = ($ts->hour > 5 && $ts->hour < 18) ? rand(200, 300) : rand(10, 30); 

                    $sensorInserts[] = ['id' => Str::uuid()->toString(), 'deviceId' => $deviceId, 'parameterId' => $paramIds['TEMP'], 'sensorTimestamp' => $ts->toDateTimeString(), 'value' => $temp, 'createdAt' => now()];
                    $sensorInserts[] = ['id' => Str::uuid()->toString(), 'deviceId' => $deviceId, 'parameterId' => $paramIds['HUMID'], 'sensorTimestamp' => $ts->toDateTimeString(), 'value' => $humid, 'createdAt' => now()];
                    $sensorInserts[] = ['id' => Str::uuid()->toString(), 'deviceId' => $deviceId, 'parameterId' => $paramIds['AMMON'], 'sensorTimestamp' => $ts->toDateTimeString(), 'value' => $ammon, 'createdAt' => now()];
                    $sensorInserts[] = ['id' => Str::uuid()->toString(), 'deviceId' => $deviceId, 'parameterId' => $paramIds['LIGHT'], 'sensorTimestamp' => $ts->toDateTimeString(), 'value' => $light, 'createdAt' => now()];
                }
                foreach (array_chunk($sensorInserts, 100) as $chunk) {
                    DB::table('iot_sensor_data')->insert($chunk);
                }
            }

            // Seed 30 day Laporan
            $hasLaporan = DB::table('laporan')->where('unitBudidayaId', $coopId)->whereDate('createdAt', now()->toDateString())->exists();
            if (!$hasLaporan) {
                for ($d = 30; $d >= 0; $d--) {
                    $date = now()->subDays($d);
                    
                    $totalPop = DB::table('unitBudidaya')->where('id', $coopId)->value('jumlah');

                    // =========================
                    // 1. LAPORAN KEMATIAN
                    // =========================
                    $mati = rand(0, 2);
                    if ($mati > 0) {
                        $lapMati = Str::uuid()->toString();
                        DB::table('laporan')->insert([
                            'id' => $lapMati,
                            'unitBudidayaId' => $coopId,
                            'userId' => $userId,
                            'tipe' => 'Kematian',
                            'createdAt' => $date->copy()->setHour(9)->toDateTimeString(),
                            'updatedAt' => now(),
                            'isDeleted' => 0,
                            'judul' => "Laporan Kematian Harian",
                        ]);
                        for ($m = 0; $m < $mati; $m++) {
                            DB::table('kematian')->insert([
                                'id' => Str::uuid()->toString(),
                                'laporanId' => $lapMati,
                                'tanggal' => $date->toDateString(),
                                'penyebab' => 'Penyakit ND/SNOT',
                                'isDeleted' => 0,
                                'createdAt' => now(),
                                'updatedAt' => now()
                            ]);
                        }
                        
                        DB::table('unitBudidaya')->where('id', $coopId)->decrement('jumlah', $mati);
                        $totalPop -= $mati;
                    }

                    // =========================
                    // 2. LAPORAN PAKAN & PANEN
                    // =========================
                    $lapPanen = Str::uuid()->toString();
                    DB::table('laporan')->insert([
                        'id' => $lapPanen,
                        'unitBudidayaId' => $coopId,
                        'userId' => $userId,
                        'tipe' => 'Panen',
                        'createdAt' => $date->copy()->setHour(16)->toDateTimeString(),
                        'updatedAt' => now(),
                        'isDeleted' => 0,
                        'judul' => "Laporan Panen Harian",
                    ]);

                    $feedPerBirdKg = rand(110, 120) / 1000;
                    $totalFeedKg = round($totalPop * $feedPerBirdKg, 0); // Convert to int since it is TINYINT
                    
                    if ($totalFeedKg > 127) $totalFeedKg = 127; // Max for signed tinyint!

                    DB::table('harianTernak')->insert([
                        'id' => Str::uuid()->toString(),
                        'laporanId' => $lapPanen,
                        'pakan' => $totalFeedKg,
                        'cekKandang' => 1,
                        'isDeleted' => 0,
                        'createdAt' => now(),
                        'updatedAt' => now()
                    ]);

                    $hdp = rand(850, 950) / 1000;
                    $totalTelur = round($totalPop * $hdp);
                    
                    $avgEggWeightKg = rand(60, 65) / 1000;
                    $totalBeratTelur = round($totalTelur * $avgEggWeightKg, 2);

                    $panenId = Str::uuid()->toString();
                    DB::table('panen')->insert([
                        'id' => $panenId,
                        'laporanId' => $lapPanen,
                        'jumlah' => $totalTelur,
                        'berat' => $totalBeratTelur,
                        'isDeleted' => 0,
                        'createdAt' => now(),
                        'updatedAt' => now()
                    ]);

                    $gradeA = round($totalTelur * 0.80);
                    $gradeB = round($totalTelur * 0.15);
                    $gradeC = round($totalTelur * 0.04);
                    $gradeO = $totalTelur - ($gradeA + $gradeB + $gradeC);

                    DB::table('panenRincianGrade')->insert([
                        ['id' => Str::uuid()->toString(), 'panenId' => $panenId, 'gradeId' => $gradeIds['Grade A'], 'jumlah' => $gradeA, 'createdAt' => now(), 'updatedAt' => now()],
                        ['id' => Str::uuid()->toString(), 'panenId' => $panenId, 'gradeId' => $gradeIds['Grade B'], 'jumlah' => $gradeB, 'createdAt' => now(), 'updatedAt' => now()],
                        ['id' => Str::uuid()->toString(), 'panenId' => $panenId, 'gradeId' => $gradeIds['Grade C'], 'jumlah' => $gradeC, 'createdAt' => now(), 'updatedAt' => now()],
                        ['id' => Str::uuid()->toString(), 'panenId' => $panenId, 'gradeId' => $gradeIds['Afkir'], 'jumlah' => max(0, $gradeO), 'createdAt' => now(), 'updatedAt' => now()],
                    ]);
                }
            }
        }
    }
}
