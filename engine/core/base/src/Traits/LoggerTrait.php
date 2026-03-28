<?php

namespace Core\Base\Traits;

trait LoggerTrait
{
    public function log(string $message): void
    {
        echo $message."\n";
    }

    //
}
// by ampol
