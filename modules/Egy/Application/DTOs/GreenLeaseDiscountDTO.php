<?php

declare(strict_types=1);

namespace Modules\Egy\Application\DTOs;

use Modules\Egy\Domain\Enums\EsgRatingTier;

/**
 * Rincian Diskon Insentif Utilitas Green Lease berbasis Kinerja ESG.
 */
final class GreenLeaseDiscountDTO
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $propertyCode,
        public readonly int $esgScoreBps,
        public readonly EsgRatingTier $tier,
        public readonly int $grossUtilityBillIdr,
        public readonly int $discountPercentageBps,
        public readonly int $discountAmountIdr,
        public readonly int $netPayableUtilityBillIdr,
        public readonly ?string $ledgerTransactionId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'property_code' => $this->propertyCode,
            'esg_score_bps' => $this->esgScoreBps,
            'tier' => $this->tier->value,
            'gross_utility_bill_idr' => $this->grossUtilityBillIdr,
            'discount_percentage_bps' => $this->discountPercentageBps,
            'discount_amount_idr' => $this->discountAmountIdr,
            'net_payable_utility_bill_idr' => $this->netPayableUtilityBillIdr,
            'ledger_transaction_id' => $this->ledgerTransactionId,
        ];
    }
}
