<?php

namespace App\Listeners;

use App\Events\AccountDeleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogAccountDeleted implements ShouldQueue
{
    public function handle(AccountDeleted $event): void
    {
        Log::warning('Account deleted', [
            'user_id' => $event->userId,
            'email' => $event->email,
        ]);
    }
}
