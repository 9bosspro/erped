<?php

declare(strict_types=1);

namespace Slave\Actions\Fortify;

use App\Models\User;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Illuminate\Http\Request;
use Slave\Services\Master\BackendAuthService;

// login auth service  ชนิด :password

class AuthenticateUser
{
    public function __construct(
        // private readonly SodiumHelper $sodium,
        private readonly BackendAuthService $authService,
    ) {}

    public function __invoke(Request $request): ?User
    {
        // return null;
        // 1. login auth service  ชนิด :password
        $result = $this->authService->login(
            $request->string('email')->value(),
            $request->string('password')->value(),
        );
        // หากล็อกอินและ Sync ข้อมูลผ่านเรียบร้อย ให้คืนค่า User กลับไปให้ Fortify ทันที
        if ($result['success'] && isset($result['user'])) {
            session()->put('type_login', 'password');
            $fingerprint = app('core.session.device_fingerprint')->fingerprintWithAgent(session()->getId());
            session(['_device_fingerprint' => $fingerprint]);
            session()->save();
            //
            $user = $result['user'];
            return $user;
        }

        // $authService = app(BackendAuthService::class);
        return null;
    }
}
