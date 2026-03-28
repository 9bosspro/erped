<?php

declare(strict_types=1);

namespace Core\Base\Events\User;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Event fired when user registration fails.
 */
class UserRegistrationFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $registerTokenId,
        public readonly string $reason,
        public readonly ?Throwable $exception = null,
        public readonly string $ipAddress = '',
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
     * Get error message.
     */
    public function getErrorMessage(): string
    {
        if ($this->exception) {
            return $this->exception->getMessage();
        }

        return $this->reason;
    }
}
