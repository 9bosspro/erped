<?php

declare(strict_types=1);

namespace Slave\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Slave\Services\Master\BackendAuthService;

/**
 * BackendLoginController — จัดการ Login/Logout ผ่าน Backend API (pppportal)
 *
 * แทนที่ Fortify default AuthenticatedSessionController
 * เพื่อให้ authentication ผ่าน Backend API เป็น single source of truth
 */
class BackendLConnectController extends Controller
{
    public function __construct(
        private readonly BackendAuthService $authService,
    ) {}

    //
}
