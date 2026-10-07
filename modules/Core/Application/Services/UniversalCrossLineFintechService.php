<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;

class UniversalCrossLineFintechService
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Cross-line instant parametric insurance claim autopilot
     * Triggers from any of the 12 lines (cold chain breach, mine weather shutdown, hotel flight delay)
     */
    public function triggerUniversalClaimPayout(
        string $lineDomain, // LOGISTICS, MINING, HOTEL, HOSPITAL, VENUE
        string $referenceEntityId,
        string $policyNumber,
        int $claimAmountIdr,
        string $reason
    ): array {
        if ($this->ledger && $claimAmountIdr > 0) {
            $this->ledger->post(new PostingDTO(
                type: 'UNIVERSAL_INSURANCE_CLAIM',
                description: "Cross-line claim [{$lineDomain}] policy {$policyNumber}: {$reason}",
                idempotencyKey: "univ_claim:{$lineDomain}:{$referenceEntityId}:{$policyNumber}",
                entries: [
                    PostingEntryDTO::forCode('reserve:insurance_pool:IDR', 'IDR', -$claimAmountIdr),
                    PostingEntryDTO::forCode("claimant:payable:{$referenceEntityId}:IDR", 'IDR', $claimAmountIdr),
                ],
                referenceType: 'insurance_claims',
                referenceId: (string) Str::uuid(),
            ));
        }

        return [
            'domain' => $lineDomain,
            'reference_id' => $referenceEntityId,
            'policy' => $policyNumber,
            'payout_amount' => $claimAmountIdr,
            'status' => 'disbursed',
        ];
    }

    /**
     * Stablecoin instant settlement for intercompany/cross-border mining or venue artist offtake
     */
    public function settleCrossLineStablecoin(
        string $sourceEntity,
        string $targetEntity,
        int $stablecoinMinorUnits,
        string $referenceMemo
    ): array {
        if ($this->ledger && $stablecoinMinorUnits > 0) {
            $this->ledger->post(new PostingDTO(
                type: 'CROSS_LINE_STABLECOIN_SETTLE',
                description: "Cross-line stablecoin clearing: {$sourceEntity} -> {$targetEntity} ({$referenceMemo})",
                idempotencyKey: "stable_clear:{$sourceEntity}:{$targetEntity}:".(string) Str::uuid(),
                entries: [
                    PostingEntryDTO::forCode("clearing:stablecoin:{$sourceEntity}:USDC", 'USDC', -$stablecoinMinorUnits),
                    PostingEntryDTO::forCode("clearing:stablecoin:{$targetEntity}:USDC", 'USDC', $stablecoinMinorUnits),
                ],
                referenceType: 'stablecoin_clearings',
                referenceId: (string) Str::uuid(),
            ));
        }

        return [
            'source' => $sourceEntity,
            'target' => $targetEntity,
            'amount_usdc' => $stablecoinMinorUnits,
            'status' => 'cleared_instant',
        ];
    }
}
