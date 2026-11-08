<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class PassengerCareCertificateResult
{
    public function __construct(
        public readonly string $category,
        public readonly int $delayMinutes,
        public readonly bool $dutyOfCareRequired,
        public readonly ?string $voucherType,
        public readonly int $voucherNominalMinorUnit,
        public readonly bool $cashCompensationRequired,
        public readonly int $cashCompensationAmountMinorUnit,
        public readonly bool $hotelRequired,
        public readonly PassengerCareCertificate $certificate
    ) {}
}
