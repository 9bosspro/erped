<?php

namespace App\DTOs;

readonly class PasswordUpdateData
{
    public function __construct(
        public string $currentPassword,
        public string $password,
    ) {}
}
