<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

use Carbon\Carbon;

final class RegisterPatientDTO
{
    public function __construct(
        public string $nik,
        public string $fullName,
        public Carbon $dateOfBirth,
        public string $bloodType,
        public int $polyclinicId,
        public int $doctorId,
        public string $paymentMethod = 'CASH',
        public ?string $bpjsCardNumber = null,
        public ?int $userId = null,
    ) {
    }
}
