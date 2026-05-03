<?php

declare(strict_types=1);

namespace Core\Base\Listeners\User;

use Core\Base\Events\User\UserRegistrationCompleted;
use Core\Base\Events\User\UserRegistrationFailed;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Listener to log user registration activity.
 */
class LogRegistrationActivity
{
    /**
     * Handle successful registration.
     */
    public function handleCompleted(UserRegistrationCompleted $event): void
    {
        Log::channel('audit')->info('USER_REGISTERED', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
            'token_id' => $event->registerTokenId,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Handle failed registration.
     */
    public function handleFailed(UserRegistrationFailed $event): void
    {
        Log::channel('audit')->warning('USER_REGISTRATION_FAILED', [
            'email' => $event->email,
            'token_id' => $event->registerTokenId,
            'reason' => $event->reason,
            'error' => $event->getErrorMessage(),
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    /**
     * ลงทะเบียน listeners สำหรับ subscriber
     *
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            UserRegistrationCompleted::class => 'handleCompleted',
            UserRegistrationFailed::class => 'handleFailed',
        ];
    }
}
