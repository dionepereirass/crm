<?php

namespace App\DTOs\Players;

readonly class UpdatePlayerDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $whatsapp = null,
        public ?string $cpf = null,
        public ?string $birthDate = null,
        public ?string $gender = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $zipCode = null,
        public ?string $status = null,
        public ?string $source = null,
        public ?string $affiliate = null,
        public ?string $promoCode = null,
        public ?array $customFields = null,
        public ?array $tagIds = null
    ) {}
}
