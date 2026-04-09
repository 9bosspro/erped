<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Core\Base\Traits\ApiResponseTraits;

abstract class BaseApiController extends Controller
{
    use ApiResponseTraits;
}
