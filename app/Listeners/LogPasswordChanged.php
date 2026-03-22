<?php

namespace App\Listeners;

use App\Events\PasswordChanged;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogPasswordChanged implements ShouldQueue
{
    public function handle(PasswordChanged $event): void
    {
        Log::info('Password changed', [
            'user_id' => $event->user->id,
        ]);

        $event->user->notify(new PasswordChangedNotification());
    }
}
