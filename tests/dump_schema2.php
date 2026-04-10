<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=smartfarm", "user", "password");
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$res = [];
foreach($tables as $t) {
    $cols = $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_COLUMN);
    $res[$t] = $cols;
}
file_put_contents("tests/schema.json", json_encode($res, JSON_PRETTY_PRINT));
echo "done\n";
