<?php

declare(strict_types=1);

namespace Core\Base\Events\User;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when user registration process starts.
 */
class UserRegistrationStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $registerTokenId,
        public readonly array $registrationData,
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
}
