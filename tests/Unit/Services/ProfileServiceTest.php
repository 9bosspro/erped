<?php

declare(strict_types=1);

use App\DTOs\ProfileUpdateData;
use App\Events\AccountDeleted;
use App\Events\ProfileUpdated;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\ProfileService;
use Illuminate\Support\Facades\Event;

describe('ProfileService', function () {
    beforeEach(function () {
        $this->repo    = Mockery::mock(UserRepositoryInterface::class);
        $this->service = new ProfileService($this->repo);
    });

    // ─── updateProfile ──────────────────────────────────────────────

    describe('updateProfile', function () {
        it('returns user unchanged when no fields changed', function () {
            $user = User::factory()->make(['name' => 'John', 'email' => 'john@test.com']);
            $data = new ProfileUpdateData('John', 'john@test.com');

            $this->repo->shouldNotReceive('update');

            $result = $this->service->updateProfile($user, $data);

            expect($result)->toBe($user);
        });

        it('calls repository update when name changes', function () {
            Event::fake();

            $user    = User::factory()->make(['name' => 'Old Name', 'email' => 'john@test.com']);
            $updated = User::factory()->make(['name' => 'New Name', 'email' => 'john@test.com']);
            $data    = new ProfileUpdateData('New Name', 'john@test.com');

            $this->repo->shouldReceive('update')
                ->once()
                ->with($user, ['name' => 'New Name', 'email' => 'john@test.com'])
                ->andReturn($updated);

            $result = $this->service->updateProfile($user, $data);

            expect($result->name)->toBe('New Name');
        });

        it('dispatches ProfileUpdated event when name changes', function () {
            Event::fake();

            $user    = User::factory()->make(['name' => 'Old', 'email' => 'test@test.com']);
            $updated = User::factory()->make(['name' => 'New', 'email' => 'test@test.com']);
            $data    = new ProfileUpdateData('New', 'test@test.com');

            $this->repo->shouldReceive('update')->once()->andReturn($updated);

            $this->service->updateProfile($user, $data);

            Event::assertDispatched(ProfileUpdated::class);
        });

        it('marks email unverified when email changes', function () {
            Event::fake();

            $user    = User::factory()->make(['name' => 'John', 'email' => 'old@test.com']);
            $updated = User::factory()->make(['name' => 'John', 'email' => 'new@test.com']);
            $data    = new ProfileUpdateData('John', 'new@test.com');

            $this->repo->shouldReceive('update')->once()->andReturn($updated);
            $this->repo->shouldReceive('markEmailAsUnverified')
                ->once()
                ->with($updated)
                ->andReturn($updated);

            $this->service->updateProfile($user, $data);
        });

        it('does not call markEmailAsUnverified when email unchanged', function () {
            Event::fake();

            $user    = User::factory()->make(['name' => 'Old', 'email' => 'same@test.com']);
            $updated = User::factory()->make(['name' => 'New', 'email' => 'same@test.com']);
            $data    = new ProfileUpdateData('New', 'same@test.com');

            $this->repo->shouldReceive('update')->once()->andReturn($updated);
            $this->repo->shouldNotReceive('markEmailAsUnverified');

            $this->service->updateProfile($user, $data);
        });

        it('dispatches ProfileUpdated with correct changed fields', function () {
            Event::fake();

            $user    = User::factory()->make(['name' => 'Old', 'email' => 'old@test.com']);
            $updated = User::factory()->make(['name' => 'New', 'email' => 'new@test.com']);
            $data    = new ProfileUpdateData('New', 'new@test.com');

            $this->repo->shouldReceive('update')->once()->andReturn($updated);
            $this->repo->shouldReceive('markEmailAsUnverified')->once()->andReturn($updated);

            $this->service->updateProfile($user, $data);

            Event::assertDispatched(ProfileUpdated::class, function ($event) {
                return in_array('name', $event->changedFields)
                    && in_array('email', $event->changedFields);
            });
        });
    });

    // ─── deleteAccount ──────────────────────────────────────────────

    describe('deleteAccount', function () {
        it('calls repository delete', function () {
            Event::fake();

            $user = User::factory()->make(['id' => 1]);

            $this->repo->shouldReceive('delete')->once()->with($user);

            $this->service->deleteAccount($user);
        });

        it('dispatches AccountDeleted event after delete', function () {
            Event::fake();

            $user = User::factory()->make(['id' => 99, 'email' => 'del@test.com']);

            $this->repo->shouldReceive('delete')->once();

            $this->service->deleteAccount($user);

            Event::assertDispatched(AccountDeleted::class, function ($event) {
                return $event->userId === 99 && $event->email === 'del@test.com';
            });
        });
    });
});
