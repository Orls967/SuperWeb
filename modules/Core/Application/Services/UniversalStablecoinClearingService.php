<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Core\Ledger\CoreTransactionTypes;
use RuntimeException;

class UniversalStablecoinClearingService
{
    public function __construct(
        protected Ledger $ledger
    ) {}

    /**
     * Real-time 24/7 cross-border stablecoin clearing with locked exchange rate
     *
     * @return array{status: string, cleared_usdc: int, equivalent_idr: int, reference: string}
     */
    public function clearCrossBorderContract(
        string $sourceEntityId,
        string $beneficiaryEntityId,
        int $usdcAmountCents,
        int $lockedRateIdrPerUsd,
        string $contractRef,
        int $quoteCreatedAtTimestamp = 0
    ): array {
        if ($usdcAmountCents <= 0 || $lockedRateIdrPerUsd <= 0) {
            throw new RuntimeException('Stablecoin clearing parameters must be strictly positive.');
        }

        // Validate locked FX quote TTL (15 minutes = 900 seconds) if timestamp provided
        if ($quoteCreatedAtTimestamp > 0 && (time() - $quoteCreatedAtTimestamp > 900)) {
            throw new RuntimeException('EXPIRED_FX_QUOTE: Stablecoin quote has expired (TTL 15m).');
        }

        $idrEquivalent = (int) floor(($usdcAmountCents * $lockedRateIdrPerUsd) / 100);
        $deterministicKey = 'stable_clear:' . hash('sha256', $sourceEntityId . ':' . $beneficiaryEntityId . ':' . $contractRef);

        $srcStableAccount = "clearing:stablecoin:{$sourceEntityId}:USDC";
        $tgtStableAccount = "clearing:stablecoin:{$beneficiaryEntityId}:USDC";

        // Multi-currency double entry: Ledger balanced across USDC clearing and intercompany settlement
        $this->ledger->post(new PostingDTO(
            type: CoreTransactionTypes::CROSS_LINE_STABLECOIN_SETTLE->value,
            description: "24/7 Cross-border clearing {$contractRef}: {$sourceEntityId} -> {$beneficiaryEntityId}",
            idempotencyKey: $deterministicKey,
            entries: [
                PostingEntryDTO::forCode($srcStableAccount, 'USDC', -$usdcAmountCents),
                PostingEntryDTO::forCode($tgtStableAccount, 'USDC', $usdcAmountCents),
                PostingEntryDTO::forCode('intercompany:payable:default:IDR', 'IDR', -$idrEquivalent),
                PostingEntryDTO::forCode('intercompany:receivable:default:IDR', 'IDR', $idrEquivalent),
            ],
            referenceType: 'stablecoin_clearings',
            referenceId: (string) hash('crc32b', $contractRef),
        ));

        return [
            'status' => 'CLEARED_INSTANT',
            'cleared_usdc' => $usdcAmountCents,
            'equivalent_idr' => $idrEquivalent,
            'reference' => $contractRef,
        ];
    }
}
