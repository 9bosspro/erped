<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Core\Base\Traits\ApiResponseTrait;

/**
 * BaseApiController — base class สำหรับ API controllers ทั้งหมด
 *
 * ให้ successResponse() / errorResponse() ผ่าน ApiResponseTraits
 * ซึ่ง delegate ไปยัง response macros ที่ลงทะเบียนโดย CoreServiceProvider
 */
abstract class BaseApiController extends Controller
{
    use ApiResponseTrait;
}
