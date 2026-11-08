<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final readonly class EventMarginCertificate
{
    /**
     * @param  array<string, int>  $revenueBreakdown
     * @param  array<string, int>  $costBreakdown
     */
    public function __construct(
        public string $proposalId,
        public int $totalRevenueIdr,
        public int $totalDirectCostIdr,
        public int $grossMarginIdr,
        public float $grossMarginPercent,
        public float $guardrailThresholdPercent,
        public bool $isCompliant,
        public string $status,
        public int $priceShortfallIdr,
        public array $revenueBreakdown,
        public array $costBreakdown,
        public string $signatureHash
    ) {}
}
