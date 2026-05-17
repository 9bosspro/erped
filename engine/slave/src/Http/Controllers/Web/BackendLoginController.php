<?php

declare(strict_types=1);

namespace Slave\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Slave\Contracts\Master\BackendAuthServiceInterface;

/**
 * BackendLoginController — จัดการ Login/Logout ผ่าน Backend API (pppportal)
 *
 * แทนที่ Fortify default AuthenticatedSessionController
 * เพื่อให้ authentication ผ่าน Backend API เป็น single source of truth
 */
class BackendLoginController extends Controller
{
    public function __construct(
        private readonly BackendAuthServiceInterface $authService,
    ) {}

    /**
     * แสดงหน้า Login
     */
    public function create(): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => true,
            'status' => session('status'),
        ]);
    }

    /**
     * Login ผ่าน Backend API
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $result = $this->authService->login(
            $request->string('email')->value(),
            $request->string('password')->value(),
        );

        if (! (bool) ($result['success'] ?? false)) {
            return back()->withErrors([
                'email' => (string) ($result['message'] ?? 'เกิดข้อผิดพลาด กรุณาลองใหม่'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    /**
     * Logout ทั้ง Frontend + Backend
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
