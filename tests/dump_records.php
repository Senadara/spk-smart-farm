<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=smartfarm", "user", "password");
$jb = $pdo->query("SELECT * FROM jenisBudidaya")->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('tests/jb.json', json_encode($jb, JSON_PRETTY_PRINT));
$ip = $pdo->query("SELECT * FROM iot_parameter")->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('tests/ip.json', json_encode($ip, JSON_PRETTY_PRINT));
echo "ok\n";
