<?php

declare(strict_types=1);

namespace Engine\Modules\User\Http\Controllers;

use App\Http\Controllers\BaseInertiaController;
use Inertia\Response as InertiaResponse;

class UserController extends BaseInertiaController
{
    protected string $pagePrefix = 'user';
    protected string $layout     = 'frontend';

    public function index(): InertiaResponse
    {
        return $this->inertia('home/index', [
            'message' => 'ยินดีต้อนรับสู่ User Area',
        ]);
    }
}
