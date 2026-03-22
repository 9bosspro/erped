<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Services\PasswordService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function __construct(
        private PasswordService $passwordService,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('settings/password');
    }

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $this->passwordService->updatePassword(
            $request->user(),
            $request->validated('password'),
        );

        return back();
    }
}
