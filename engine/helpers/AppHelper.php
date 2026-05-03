<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Global Helper Functions (Laravel Standard Edition 2025)
|--------------------------------------------------------------------------
|
| ชุดฟังก์ชันช่วยเหลือหลักที่ใช้บ่อยในโปรเจกต์ Laravel
| ฟังก์ชันเฉพาะทางถูกแยกไปยังไฟล์ย่อย:
|
| - ArrayHelper.php    : Array utilities
| - StringHelper.php   : String utilities
| - PathHelper.php     : Path utilities
| - SecurityHelper.php : Security/Encryption utilities
| - JsonHelper.php     : JSON utilities
| - ThaiHelper.php     : Thai-specific utilities
| - DebugHelper.php    : Debug utilities
|
*/
// Load order matters! Check dependencies before changing.
// CommonHelper → PathHelper → JsonHelper → StringHelper → ArrayHelper → ThaiHelper → DebugHelper

require_once __DIR__.'/CommonHelper.php';     // current_local, is_api_request, dispatch_safe (no dependencies)
require_once __DIR__.'/PathHelper.php';       // gen_path, normalize_path (no dependencies)
require_once __DIR__.'/JsonHelper.php';       // return_success, return_error, is_jsons (no dependencies)
require_once __DIR__.'/StringHelper.php';     // ppp_strlen, trim_null, data_ready (no dependencies)
require_once __DIR__.'/ArrayHelper.php';      // array_some, array_every, gen_subset_arrays (no dependencies)
require_once __DIR__.'/ThaiHelper.php';       // check_citizen_id, remaining_time_text (no dependencies)
require_once __DIR__.'/DebugHelper.php';      // tt, ttt — depends on JsonHelper (is_jsons)
// require_once __DIR__ . '/HashHelper.php';    // [REMOVED] → ย้ายไป Core\Base\Support\Helpers\Crypto\HashHelper
// require_once __DIR__ . '/CryptHelper.php';   // [REMOVED] → ย้ายไป Core\Base\Support\Helpers\Crypto\SodiumHelper
// require_once __DIR__ . '/JwtHelper.php';     // [REMOVED] → ย้ายไป Core\Base\Support\Helpers\Crypto\JwtHelper
// require_once __DIR__ . '/RsalHelper.php';    // [REMOVED] → ย้ายไป Core\Base\Support\Helpers\Crypto\SodiumHelper
