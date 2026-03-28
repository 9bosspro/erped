<?php

declare(strict_types=1);

namespace Core\Base\Listeners\User;

use Core\Base\Events\User\UserRegistrationCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Listener to send welcome email after user registration.
 */
class SendWelcomeEmail implements ShouldQueue
{
    /**
     * The queue name.
     */
    public string $queue = 'emails'; // emails

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    /**
     * Handle the event.
     */
    public function handle(UserRegistrationCompleted $event): void
    {
        $user = $event->user;

        // Check if welcome email mailable exists
        $mailableClass = config('core.mail.welcome_email', \Core\Base\Mail\WelcomeEmail::class);

        if (! class_exists($mailableClass)) {
            Log::warning('Welcome email mailable not found', [
                'class' => $mailableClass,
                'user_id' => $user->id,
            ]);

            return;
        }

        Mail::to($user->email)->send(
            new $mailableClass($user, $event->registrationData),
        );

        Log::info('Welcome email sent', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(UserRegistrationCompleted $event, Throwable $exception): void
    {
        Log::error('Failed to send welcome email', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(UserRegistrationCompleted $event): bool
    {
        return config('core.events.queue_emails', true);
    }
}
