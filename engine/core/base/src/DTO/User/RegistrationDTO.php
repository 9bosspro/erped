<?php

declare(strict_types=1);

namespace Core\Base\DTO\User;

use Core\Base\DTO\BaseDTO;

class RegistrationDTO extends BaseDTO
{
    public function __construct(
        public readonly string $name_th,
        public readonly string $password,
        public readonly ?string $name_en = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * แปลงจาก Request หรือ Array ให้เป็น DTO
     */
    public static function fromArray(array $data): static
    {
        return new static(
            name_th: $data['name_th'],
            password: $data['password'],
            name_en: $data['name_en'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }
}
