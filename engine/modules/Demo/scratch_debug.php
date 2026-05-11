<?php

use Illuminate\Support\Facades\Cache;
use Slave\Contracts\Master\TokenFlow;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// ดึงกุญแจที่ User เจอมาทดสอบแบบ RAW
$key = "master_token:jwt:019ded87-7d19-73d4-88eb-f2a97889f49e:794dd4e3002ee09879219a5401228358";
$manifestKey = "master_manifest:019ded87-7d19-73d4-88eb-f2a97889f49e";

echo "--- 🔍 CACHE DIAGNOSTIC START ---\n";
echo "1. Cache Driver in .env: " . config('cache.default') . "\n";

$rawContent = Cache::get($key);
echo "2. Raw Cache Content for Token Key: " . var_export($rawContent, true) . "\n";

$manifestContent = Cache::get($manifestKey);
echo "3. Raw Manifest Content: " . var_export($manifestContent, true) . "\n";

// ทดสอบการเขียนและดึงค่ากลับมาทันทีว่า Cache Engine ทำงานปกติหรือไม่
Cache::put('debug_test_write', ['hello' => 'world'], 600);
$writeTest = Cache::get('debug_test_write');
echo "4. Self-Write Test Result: " . var_export($writeTest, true) . "\n";

echo "--- 🏁 DIAGNOSTIC END ---\n";
