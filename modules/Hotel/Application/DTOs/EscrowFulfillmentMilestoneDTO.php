<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final class EscrowFulfillmentMilestoneDTO
{
    public function __construct(
        public readonly string $packageBookingId,
        public readonly string $vendorType,
        public readonly string $vendorReference,
        public readonly int $releasedAmountIdr,
        public readonly int $remainingEscrowBalanceIdr,
        public readonly string $fulfillmentTimestamp,
        public readonly string $escrowAccountCode
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'package_booking_id' => $this->packageBookingId,
            'vendor_type' => $this->vendorType,
            'vendor_reference' => $this->vendorReference,
            'released_amount_idr' => $this->releasedAmountIdr,
            'remaining_escrow_balance_idr' => $this->remainingEscrowBalanceIdr,
            'fulfillment_timestamp' => $this->fulfillmentTimestamp,
            'escrow_account_code' => $this->escrowAccountCode,
        ];
    }
}
