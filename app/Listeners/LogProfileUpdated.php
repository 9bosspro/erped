<?php

namespace App\Listeners;

use App\Events\ProfileUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogProfileUpdated implements ShouldQueue
{
    public function handle(ProfileUpdated $event): void
    {
        Log::info('Profile updated', [
            'user_id' => $event->user->id,
            'changed_fields' => $event->changedFields,
        ]);
    }
}
