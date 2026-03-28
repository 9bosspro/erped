<?php

// by ampol

namespace Core\Base\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

//

trait HandlesTimezones
{
    /**
     * Write code on Method
     *
     * @return response()
     */
    public function getCreatedAtAttribute($value)
    {
        return $this->convertTimeZoneToUserTimezone($value);
    }

    /**
     * Write code on Method
     *
     * @return response()
     */
    public function getUpdatedAtAttribute($value)
    {
        return $this->convertTimeZoneToUserTimezone($value);
    }

    /**
     * Write code on Method
     *
     * @return response()
     */
    protected function convertTimeZoneToUserTimezone($value)
    {
        $timezone = Auth::check() & Auth::user()->timezone ? Auth::user()->timezone : config('app.timezone');

        return Carbon::parse($value)->timezone($timezone)->format('Y-m-d H:i:s');
    }
}
