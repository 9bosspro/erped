<?php

namespace App\Services;

use App\DTOs\ProfileUpdateData;
use App\Events\AccountDeleted;
use App\Events\ProfileUpdated;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class ProfileService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    public function updateProfile(User $user, ProfileUpdateData $data): User
    {
        $changedFields = [];

        if ($user->name !== $data->name) {
            $changedFields[] = 'name';
        }

        $emailChanged = $user->email !== $data->email;
        if ($emailChanged) {
            $changedFields[] = 'email';
        }

        $user = $this->userRepository->update($user, [
            'name' => $data->name,
            'email' => $data->email,
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null;
            $user->save();
        }

        if (! empty($changedFields)) {
            ProfileUpdated::dispatch($user, $changedFields);
        }

        return $user;
    }

    public function deleteAccount(User $user): void
    {
        $userId = $user->id;
        $email = $user->email;

        $this->userRepository->delete($user);

        AccountDeleted::dispatch($userId, $email);
    }
}
