<?php

declare(strict_types=1);

/* use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; */

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
// StringHelper → JsonHelper → SecurityHelper → DebugHelper

require_once __DIR__ . '/CommonHelper.php';     // ppp_strlen (used by JsonHelper)
require_once __DIR__ . '/PathHelper.php';       // no dependencies
// require_once __DIR__ . '/DateHelper.php';      // Empty File
require_once __DIR__ . '/JsonHelper.php';       // json_encode_th, is_jsons (used by Security, Debug)
require_once __DIR__ . '/HashHelper.php';       //
require_once __DIR__ . '/CryptHelper.php';      //
require_once __DIR__ . '/JwtHelper.php';      //
// require_once __DIR__ . '/RsalHelper.php';      // Empty File
require_once __DIR__ . '/StringHelper.php';     // ppp_strlen (used by JsonHelper)
require_once __DIR__ . '/ArrayHelper.php';      // no dependencies
require_once __DIR__ . '/ThaiHelper.php';       // no dependencies
require_once __DIR__ . '/DebugHelper.php';      // depends on JsonHelper
