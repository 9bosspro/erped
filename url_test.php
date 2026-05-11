<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$baseUrl = "http://myserver.com"; // No trailing slash
$endpoint = "api/v1/test"; // No leading slash

// Mock the request to intercept the full URL
Http::fake();

Http::baseUrl($baseUrl)->get($endpoint);

$recorded = Http::recorded()->first();
echo "RAW BASE URL: " . $baseUrl . "\n";
echo "ENDPOINT: " . $endpoint . "\n";
echo "FINAL CONCATENATED URL: " . $recorded[0]->url() . "\n";
