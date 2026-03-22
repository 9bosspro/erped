<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class UserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return Cache::remember(
            "user:{$id}",
            config('myapp.cache.user_ttl'),
            fn () => User::find($id),
        );
    }

    public function findByEmail(string $email): ?User
    {
        return Cache::remember(
            "user:email:{$email}",
            config('myapp.cache.user_ttl'),
            fn () => User::where('email', $email)->first(),
        );
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $oldEmail = $user->email;

        $user->fill($data);
        $user->save();

        $this->invalidateCache($user, $oldEmail);

        return $user;
    }

    public function delete(User $user): void
    {
        $this->invalidateCache($user);
        $user->delete();
    }

    private function invalidateCache(User $user, ?string $oldEmail = null): void
    {
        Cache::forget("user:{$user->id}");
        Cache::forget("user:email:{$user->email}");

        if ($oldEmail && $oldEmail !== $user->email) {
            Cache::forget("user:email:{$oldEmail}");
        }
    }
}
