<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = Illuminate\Support\Facades\DB::select("SHOW TABLES");
$schema = [];
foreach($tables as $table) {
    $tableName = current((array)$table);
    $columns = Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM `{$tableName}`");
    $schema[$tableName] = array_map(function($col) { return $col->Field; }, $columns);
}

file_put_contents('tests/schema.json', json_encode($schema, JSON_PRETTY_PRINT));
echo "Schema dumped successfully.\n";
