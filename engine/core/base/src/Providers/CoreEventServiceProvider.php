<?php

declare(strict_types=1);

namespace Core\Base\Providers;

// User Events
use Core\Base\Events\User\UserRegistrationCompleted;
// User Listeners
use Core\Base\Listeners\Storage\LogUploadActivity;
use Core\Base\Listeners\Storage\NotifyUploadComplete;
// Storage Listeners
use Core\Base\Listeners\User\LogRegistrationActivity;
use Core\Base\Listeners\User\SendWelcomeEmail;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;

/**
 * Event Service Provider for Core Domain Events.
 */
class CoreEventServiceProvider extends EventServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // User Registration Events
        UserRegistrationCompleted::class => [
            SendWelcomeEmail::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        // User Activity Subscriber
        LogRegistrationActivity::class,

        // Storage Activity Subscriber
        LogUploadActivity::class,
        NotifyUploadComplete::class,
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

    /**
     * Get the listener directories that should be used to discover events.
     */
    protected function discoverEventsWithin(): array
    {
        return [
            $this->app->basePath('engine/core/base/src/Listeners'),
        ];
    }
}
