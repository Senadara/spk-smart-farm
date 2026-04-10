<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=smartfarm", "user", "password");
$ub = $pdo->query("SELECT id, jenisBudidayaId, nama, status, isDeleted, jumlah FROM unitBudidaya")->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('tests/ub.json', json_encode($ub, JSON_PRETTY_PRINT));
echo "ok\n";
