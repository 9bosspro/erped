<?php

declare(strict_types=1);

namespace Core\Base\Events\User;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when user registration is completed successfully.
 */
class UserRegistrationCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly array $registrationData,
        public readonly string $registerTokenId,
        public readonly string $ipAddress,
        public readonly ?string $userAgent = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }

    /**
     * Get user email.
     */
    public function getEmail(): string
    {
        return $this->user->email;
    }

    /**
     * Get user ID.
     */
    public function getUserId(): string
    {
        return $this->user->id;
    }
}
