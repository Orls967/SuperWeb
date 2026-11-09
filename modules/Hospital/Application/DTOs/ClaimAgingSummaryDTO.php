<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class ClaimAgingSummaryDTO
{
    /**
     * @phpstan-param list<array<string, mixed>> $unpaidClaims
     */
    public function __construct(
        public readonly int $current0To30Idr,
        public readonly int $pastDue31To60Idr,
        public readonly int $pastDue61To90Idr,
        public readonly int $pastDueOver90Idr,
        public readonly int $totalOutstandingIdr,
        public readonly int $estimatedImpairmentAllowanceIdr,
        public readonly array $unpaidClaims = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'current_0_to_30_idr' => $this->current0To30Idr,
            'past_due_31_to_60_idr' => $this->pastDue31To60Idr,
            'past_due_61_to_90_idr' => $this->pastDue61To90Idr,
            'past_due_over_90_idr' => $this->pastDueOver90Idr,
            'total_outstanding_idr' => $this->totalOutstandingIdr,
            'estimated_impairment_allowance_idr' => $this->estimatedImpairmentAllowanceIdr,
            'claim_count' => count($this->unpaidClaims),
        ];
    }
}
