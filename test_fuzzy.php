<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$engine = app(\App\Services\Fuzzy\MamdaniEngine::class);

$inputs = [
    'suhu' => 24, 'kelembapan' => 65, 'amonia' => 5,
    'hdp' => 95, 'pakan' => 115, 'mortalitas' => 0.1
];

try {
    $result = $engine->processCascaded($inputs);

    // Lihat hasil
    dump($result['lingkungan']['label'] ?? 'N/A');
    dump($result['kesehatan']['label'] ?? 'N/A');
    dump($result['kausalitas']['label'] ?? 'N/A');

    // Lihat fuzzified values
    dump($result['lingkungan']['fuzzified'] ?? 'N/A');

    // Test narrative
    $narrator = app(\App\Services\Fuzzy\NarrativeGenerator::class);
    echo $narrator->generate($result, 'Kandang Layer A');
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
