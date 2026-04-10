<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=smartfarm", "user", "password");

try {
    $stmt = $pdo->query("SELECT id FROM jenisBudidaya WHERE nama LIKE '%Ayam Petelur%'");
    $jbId = $stmt->fetch(PDO::FETCH_ASSOC)['id'] ?? exit("Ayam Petelur missing\n");

    $coopId1 = "ub-dummy-" . rand(100, 999);
    $pdo->exec("INSERT INTO unitBudidaya (id, jenisBudidayaId, nama, jumlah, status, isDeleted, createdAt, updatedAt) VALUES ('$coopId1', '$jbId', 'Kandang Petelur Dummy 1', 480, 1, 0, NOW(), NOW())");

    $usrId = ($pdo->query("SELECT id FROM user LIMIT 1")->fetch(PDO::FETCH_ASSOC)['id']) ?? 'usr-none';

    $lapYes = "lap-dummy-" . rand(1000, 9999);
    $lapToday = "lap-dummy-" . rand(1000, 9999);
    
    $pdo->exec("INSERT INTO laporan (id, unitBudidayaId, userId, judul, tipe, isDeleted, createdAt, updatedAt) VALUES ('$lapToday', '$coopId1', '$usrId', 'Laporan Harian (Dummy)', 'harian', 0, NOW(), NOW())");
    $pdo->exec("INSERT INTO laporan (id, unitBudidayaId, userId, judul, tipe, isDeleted, createdAt, updatedAt) VALUES ('$lapYes', '$coopId1', '$usrId', 'Laporan Harian (Dummy) Yes', 'harian', 0, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY))");

    $pdo->exec("INSERT INTO panen (id, laporanId, komoditasId, jumlah, berat, isDeleted, createdAt, updatedAt) VALUES ('pan-".rand(1000,9999)."', '$lapToday', NULL, 450, 27.5, 0, NOW(), NOW())");
    $pdo->exec("INSERT INTO panen (id, laporanId, komoditasId, jumlah, berat, isDeleted, createdAt, updatedAt) VALUES ('pan-".rand(1000,9999)."', '$lapYes', NULL, 440, 26.8, 0, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY))");

    $pdo->exec("INSERT INTO harianTernak (id, laporanId, pakan, cekKandang, isDeleted, createdAt, updatedAt) VALUES ('ht-".rand(1000,9999)."', '$lapToday', 55.4, 1, 0, NOW(), NOW())");
    $pdo->exec("INSERT INTO harianTernak (id, laporanId, pakan, cekKandang, isDeleted, createdAt, updatedAt) VALUES ('ht-".rand(1000,9999)."', '$lapYes', 55.0, 1, 0, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY))");

    $pdo->exec("INSERT INTO kematian (id, laporanId, tanggal, penyebab, isDeleted, createdAt, updatedAt) VALUES ('kem-".rand(100,999)."', '$lapYes', DATE_SUB(NOW(), INTERVAL 1 DAY), 'Sakit', 0, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY))");

    // add temperature IoT data
    $deviceId = "dev-dummy-".rand(100,999);
    $pdo->exec("INSERT INTO iot_device (id, unitBudidayaId, deviceCode, deviceName, status, createdAt, updatedAt) VALUES ('$deviceId', '$coopId1', 'DEV-TEMP', 'Dummy Temp Sensor', 'active', NOW(), NOW())");
    
    $paramId = ($pdo->query("SELECT id FROM iot_parameter WHERE parameterCode = 'TEMP' LIMIT 1")->fetch(PDO::FETCH_ASSOC)['id']) ?? null;
    if ($paramId) {
        $pdo->exec("INSERT INTO iot_sensor_data (id, deviceId, parameterId, value, sensorTimestamp, isDeleted, createdAt, updatedAt) VALUES ('dat-".rand(100,999)."', '$deviceId', '$paramId', 24.5, NOW(), 0, NOW(), NOW())");
        $pdo->exec("INSERT INTO iot_sensor_data (id, deviceId, parameterId, value, sensorTimestamp, isDeleted, createdAt, updatedAt) VALUES ('dat-".rand(100,999)."', '$deviceId', '$paramId', 25.1, DATE_SUB(NOW(), INTERVAL 1 DAY), 0, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY))");
    }

    echo "ALL OK!\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }
