<?php
require __DIR__ . "/vendor/autoload.php";
$app = require_once __DIR__ . "/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
echo "Boot OK\n";
try {
    $request = Illuminate\Http\Request::create("/iot/devices", "GET");
    $response = $kernel->handle($request);
    echo "Status: " . $response->getStatusCode() . "\n";
} catch (Throwable $e) {
    echo "Error: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
}
