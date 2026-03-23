<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\BackendApi\BackendAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BackendLoginController — จัดการ Login/Logout ผ่าน Backend API (pppportal)
 *
 * แทนที่ Fortify default AuthenticatedSessionController
 * เพื่อให้ authentication ผ่าน Backend API เป็น single source of truth
 */
class BackendLoginController extends Controller
{
    public function __construct(
        private readonly BackendAuthService $authService,
    ) {}

    /**
     * แสดงหน้า Login
     */
    public function create(): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => true,
            'status'           => session('status'),
        ]);
    }

    /**
     * Login ผ่าน Backend API
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        $result = $this->authService->login(
            $request->input('email'),
            $request->input('password'),
        );

        if (! $result['success']) {
            return back()->withErrors([
                'email' => $result['message'],
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
        $this->authService->logout();

        return redirect('/');
    }
}
