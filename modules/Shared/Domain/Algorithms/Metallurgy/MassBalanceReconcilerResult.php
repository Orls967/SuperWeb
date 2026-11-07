<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Metallurgy;

final class MassBalanceReconcilerResult
{
    public readonly int $reconciledTailingsKg;
    public readonly int $feedMetalKg;
    public readonly int $toleranceKg;
    public readonly bool $isBalanced;

    /**
     * @param  array<string, mixed>  $certificate
     */
    public function __construct(
        public readonly int $rawFeedKg,
        public readonly int $rawConcentrateKg,
        public readonly int $rawTailingKg,
        public readonly int $reconciledFeedKg,
        public readonly int $reconciledConcentrateKg,
        public readonly int $reconciledTailingKg,
        public readonly int $discrepancyKg,
        public readonly int $recoveryBps,
        public readonly bool $requiresInvestigation,
        public readonly bool $requiresApproval,
        public readonly array $certificate
    ) {
        $this->reconciledTailingsKg = $reconciledTailingKg;
        $this->feedMetalKg = $rawFeedKg;
        $this->toleranceKg = (int) ($certificate['tolerance_kg'] ?? 100);
        $this->isBalanced = ! $requiresInvestigation;
    }
}
