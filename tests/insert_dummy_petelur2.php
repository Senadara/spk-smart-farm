<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=smartfarm", "user", "password");

try {
    // 1. Get Jenis Budidaya ID for Ayam Petelur
    $stmt = $pdo->query("SELECT id FROM jenisBudidaya WHERE nama LIKE '%Ayam Petelur%'");
    $jenisBudidaya = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$jenisBudidaya) {
        die("Ayam Petelur not found\n");
    }

    $jbId = $jenisBudidaya['id'];

    // 2. Insert dummy coops for Ayam Petelur
    $coopId1 = "ub-dummy-" . rand(100, 999);
    $pdo->exec("INSERT INTO unitBudidaya (id, jenisBudidayaId, nama, jumlah, status, isDeleted, createdAt, updatedAt) 
    VALUES ('$coopId1', '$jbId', 'Kandang Petelur Dummy 1', 480, 1, 0, NOW(), NOW())");

    // 3. User ID mock 
    $userId = "usr-001"; // random assumption
    $stmt = $pdo->query("SELECT id FROM user LIMIT 1");
    $usr = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usr) { $userId = $usr['id']; }

    // 4. Laporan for today and yesterday
    $today = date('Y-m-d H:i:s');
    $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));

    $lapIdToday = "lap-dummy-" . rand(1000, 9999);
    $pdo->exec("INSERT INTO laporan (id, unitBudidayaId, userId, judul, tipe, isDeleted, createdAt, updatedAt) 
    VALUES ('$lapIdToday', '$coopId1', '$userId', 'Laporan Harian (Dummy) Today', 'harian', 0, NOW(), NOW())");

    $lapIdYes = "lap-dummy-" . rand(1000, 9999);
    $pdo->exec("INSERT INTO laporan (id, unitBudidayaId, userId, judul, tipe, isDeleted, createdAt, updatedAt) 
    VALUES ('$lapIdYes', '$coopId1', '$userId', 'Laporan Harian (Dummy) Yesterday', 'harian', 0, '$yesterday', '$yesterday')");

    // 5. Panen today (eggs)
    $pdo->exec("INSERT INTO panen (id, laporanId, komoditasId, jumlah, berat, isDeleted, createdAt, updatedAt) 
    VALUES ('pan-".rand(1000,9999)."', '$lapIdToday', NULL, 450, 27.5, 0, NOW(), NOW())");

    // Panen yesterday
    $pdo->exec("INSERT INTO panen (id, laporanId, komoditasId, jumlah, berat, isDeleted, createdAt, updatedAt) 
    VALUES ('pan-".rand(1000,9999)."', '$lapIdYes', NULL, 440, 26.8, 0, '$yesterday', '$yesterday')");

    // 6. HarianTernak (pakan)
    $pdo->exec("INSERT INTO harianTernak (id, laporanId, pakan, cekKandang, isDeleted, createdAt, updatedAt) 
    VALUES ('ht-".rand(1000,9999)."', '$lapIdToday', 55.4, 'ok', 0, NOW(), NOW())");

    $pdo->exec("INSERT INTO harianTernak (id, laporanId, pakan, cekKandang, isDeleted, createdAt, updatedAt) 
    VALUES ('ht-".rand(1000,9999)."', '$lapIdYes', 55.0, 'ok', 0, '$yesterday', '$yesterday')");

    // 7. Kematian
    $pdo->exec("INSERT INTO kematian (id, laporanId, tanggal, penyebab, isDeleted, createdAt, updatedAt) 
    VALUES ('kem-".rand(100,999)."', '$lapIdYes', '$yesterday', 'Sakit', 0, '$yesterday', '$yesterday')");

    echo "Dummy metrics data inserted successfully.\n";
} catch (Exception $e) {
    echo "Error inserting dummy data: " . $e->getMessage() . "\n";
}
