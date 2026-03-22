<?php

namespace App\Services;

use App\Events\PasswordChanged;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class PasswordService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    public function updatePassword(User $user, string $password): void
    {
        $this->userRepository->update($user, [
            'password' => $password,
        ]);

        PasswordChanged::dispatch($user);
    }
}
