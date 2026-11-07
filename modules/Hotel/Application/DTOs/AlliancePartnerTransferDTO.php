<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final readonly class AlliancePartnerTransferDTO
{
    public function __construct(
        public string $transactionId,
        public string $travelPassId,
        public string $partnerCode,
        public string $memberPartnerAccount,
        public int $pointsDebited,
        public int $partnerUnitsCredited,
        public int $transferFeeIdr,
        public int $netPayableIdr,
        public string $status,
        public string $certificateHash
    ) {}
}
