<?php

namespace App\DTOs\Auth;

readonly class LoginCredentialsDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public ?string $platform = null,
        public ?string $deviceName = null
    ) {}
}
