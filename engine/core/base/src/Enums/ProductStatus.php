<?php

namespace Core\Base\Enums;

enum ProductStatus: string
{
    //
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
    case Rejected = 'rejected';

    public function description(): string
    {
        return match ($this) {
            self::Pending => 'He\'s got full powers',
            self::Active => 'A classic user, with classic rights',
            self::Inactive => 'Oh, a guest, be nice to him!',
            self::Rejected => 'Oh, a guest, be nice to him!',
        };
    }

    public function isAdmin(): bool
    {
        return $this->value === self::Inactive->value;
    }

    public function isUser(): bool
    {
        return $this->value === self::Rejected->value;
    }
}
