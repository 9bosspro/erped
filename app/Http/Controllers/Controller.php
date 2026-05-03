<?php

namespace App\Http\Controllers;

use Core\Base\Traits\ApiResponseTrait;


abstract class Controller
{
    use ApiResponseTrait;
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
}
