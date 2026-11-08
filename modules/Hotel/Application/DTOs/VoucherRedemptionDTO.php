<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final class VoucherRedemptionDTO
{
    public function __construct(
        public string $voucherId,
        public string $voucherCode,
        public string $travelPassId,
        public string $catalogItemCode,
        public string $catalogItemName,
        public string $category,
        public int $pointsRedeemed,
        public int $voucherFaceValueIdr,
        public string $validUntil,
        public string $qrPayload,
        public string $status
    ) {}
}
