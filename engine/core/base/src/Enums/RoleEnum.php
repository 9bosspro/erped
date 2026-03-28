<?php

namespace Core\Base\Enums;

enum RoleEnum: string
{
    case SuperADMIN = 'superadmin';
    case ADMIN = 'admin';
    case USER = 'user';
    case GUEST = 'guest';

    public function description(): string
    {
        return match ($this) {
            self::SuperADMIN => 'He\'s got full powers',
            self::ADMIN => 'He\'s got full powers',
            self::USER => 'A classic user, with classic rights',
            self::GUEST => 'Oh, a guest, be nice to him!',
        };
    }
}
