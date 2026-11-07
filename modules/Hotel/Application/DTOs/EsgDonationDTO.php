<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final class EsgDonationDTO
{
    public function __construct(
        public string $certificateId,
        public string $travelPassId,
        public string $programCode,
        public int $pointsDonated,
        public float $kgCo2Offset,
        public int $donationValueIdr,
        public string $carbonRegistrySerial,
        public string $treePlantingLocation,
        public string $issuedAt
    ) {}
}

