<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $changedFields
     */
    public function __construct(
        public User $user,
        public array $changedFields,
    ) {}
}
