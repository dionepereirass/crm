<?php

namespace App\DTOs\Players;

readonly class CreatePlayerDTO
{
    public function __construct(
        public int $platformId,
        public string $externalId,
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $whatsapp = null,
        public ?string $cpf = null,
        public ?string $birthDate = null,
        public ?string $gender = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $zipCode = null,
        public string $status = 'ACTIVE',
        public ?string $source = null,
        public ?string $affiliate = null,
        public ?string $promoCode = null,
        public array $customFields = [],
        public array $tagIds = []
    ) {}
}
