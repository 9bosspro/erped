<?php

namespace App\DTOs;

use App\Http\Requests\Settings\ProfileUpdateRequest;

readonly class ProfileUpdateData
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}

    public static function fromRequest(ProfileUpdateRequest $request): self
    {
        /** @var array{name: string, email: string} $validated */
        $validated = $request->validated();

        return new self(
            name:  $validated['name'],
            email: $validated['email'],
        );
    }
}
