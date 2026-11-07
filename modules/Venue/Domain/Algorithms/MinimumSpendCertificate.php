<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

final readonly class MinimumSpendCertificate
{
    public function __construct(
        public int $minimumSpendIdr,
        public int $depositHeldIdr,
        public int $actualSpendIdr,
        public bool $isNoShow,
        public int $totalBillIdr,
        public int $capturedDepositIdr,
        public int $additionalChargeIdr,
        public int $shortfallIdr,
        public int $forfeitedDepositIdr,
        public int $refundedDepositIdr,
        public string $hmacSignature,
    ) {}
}
