<?php

namespace Core\Base\DTOs\User;

class RegistrationDTO
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
    public static function fromArray(array $data): self
    {
        return new self(
            name_th: $data['name_th'],
            password: $data['password'],
            name_en: $data['name_en'] ?? $data['name_th'],
            metadata: $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'name_th' => $this->name_th,
            'password' => $this->password,
            'name_en' => $this->name_en,
            'metadata' => $this->metadata,
        ];
    }
}
