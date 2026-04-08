<?php

declare(strict_types=1);

use App\DTOs\PasswordUpdateData;
use App\Events\PasswordChanged;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\PasswordService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

describe('PasswordService', function () {
    beforeEach(function () {
        $this->repo    = Mockery::mock(UserRepositoryInterface::class);
        $this->service = new PasswordService($this->repo);
    });

    // ─── updatePassword ─────────────────────────────────────────────

    describe('updatePassword', function () {
        it('stores hashed password — not plain text', function () {
            Event::fake();

            $user      = User::factory()->make();
            $plaintext = 'super-secret-123';
            $data      = new PasswordUpdateData('old', $plaintext);

            $capturedAttrs = null;

            $this->repo->shouldReceive('update')
                ->once()
                ->andReturnUsing(function (mixed $_, array $attrs) use ($user, &$capturedAttrs) {
                    $capturedAttrs = $attrs;

                    return $user;
                });

            $this->service->updatePassword($user, $data);

            expect($capturedAttrs)->toHaveKey('password');
            expect($capturedAttrs['password'])->not->toBe($plaintext);
            expect(Hash::check($plaintext, $capturedAttrs['password']))->toBeTrue();
        });

        it('calls repository update exactly once', function () {
            Event::fake();

            $user = User::factory()->make();
            $data = new PasswordUpdateData('old', 'new-password');

            $this->repo->shouldReceive('update')
                ->once()
                ->andReturn($user);

            $this->service->updatePassword($user, $data);
        });

        it('dispatches PasswordChanged event with correct user', function () {
            Event::fake();

            $user = User::factory()->make();
            $data = new PasswordUpdateData('old', 'new-password');

            $this->repo->shouldReceive('update')->once()->andReturn($user);

            $this->service->updatePassword($user, $data);

            Event::assertDispatched(PasswordChanged::class, function ($event) use ($user) {
                return $event->user->is($user);
            });
        });

        it('dispatches PasswordChanged event exactly once', function () {
            Event::fake();

            $user = User::factory()->make();
            $data = new PasswordUpdateData('old', 'new-password');

            $this->repo->shouldReceive('update')->once()->andReturn($user);

            $this->service->updatePassword($user, $data);

            Event::assertDispatchedTimes(PasswordChanged::class, 1);
        });
    });
});
