<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final readonly class TravelPassRegistrationDTO
{
    public function __construct(
        public string $userReference,
        public string $initialTier = 'SILVER',
        public ?string $nationalIdentity = null
    ) {}
}
