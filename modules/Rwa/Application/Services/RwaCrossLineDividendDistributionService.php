<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Services;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Rwa\Ledger\RwaTransactionTypes;
use RuntimeException;

class RwaCrossLineDividendDistributionService
{
    public function __construct(
        protected Ledger $ledger
    ) {}

    /**
     * Distribute net operational revenue from any of the 12 lines to RWA token holders pro-rata
     * Enforces the mathematical dust invariant: sum(allocated) + dust == total_net_revenue
     *
     * @return array{asset_code: string, distributed_amount_idr: int, dust_reserved_idr: int, recipients_count: int}
     */
    public function distributeOperationalDividends(
        int $rwaAssetId,
        int $totalNetRevenueIdr,
        string $sourceBatchRef
    ): array {
        if ($totalNetRevenueIdr <= 0) {
            throw new RuntimeException('Total net revenue must be positive.');
        }

        $asset = RwaAsset::query()->findOrFail($rwaAssetId);
        $totalIssuedUnits = (int) ($asset->total_supply_tokens ?? $asset->total_units ?? 0);
        if ($totalIssuedUnits <= 0) {
            $symbol = (string) ($asset->token_symbol ?? $asset->asset_code ?? $asset->id);
            throw new RuntimeException("RWA asset {$symbol} has no active token units.");
        }

        $holdings = RwaHolding::query()
            ->where('rwa_asset_id', $rwaAssetId)
            ->where(function ($query) {
                $query->where('token_balance', '>', 0)
                    ->orWhere('units', '>', 0);
            })
            ->get();

        $totalAllocated = 0;
        $entries = [];
        $symbol = (string) ($asset->token_symbol ?? $asset->asset_code ?? (string) $asset->id);

        // Debit the revenue settlement source account
        $entries[] = PostingEntryDTO::forCode(
            'rwa:dividend_source:default:IDR',
            'IDR',
            -$totalNetRevenueIdr
        );

        foreach ($holdings as $holding) {
            $holdingUnits = (int) ($holding->token_balance ?? $holding->units ?? 0);
            if ($holdingUnits <= 0) {
                continue;
            }

            // Integer division floor to prevent fractional money
            $payout = (int) floor(($totalNetRevenueIdr * $holdingUnits) / $totalIssuedUnits);
            if ($payout > 0) {
                $totalAllocated += $payout;
                $entries[] = PostingEntryDTO::forCode(
                    "wallet:user:{$holding->user_id}:IDR",
                    'IDR',
                    $payout
                );
            }
        }

        // Dust reserve guarantees exact zero-sum balance
        $dust = $totalNetRevenueIdr - $totalAllocated;
        if ($dust > 0) {
            $entries[] = PostingEntryDTO::forCode(
                'rwa:rounding_reserve:IDR',
                'IDR',
                $dust
            );
        }

        $deterministicKey = 'rwa_div:' . $symbol . ':' . hash('sha256', $sourceBatchRef);

        $this->ledger->post(new PostingDTO(
            type: RwaTransactionTypes::RWA_DIVIDEND->value,
            description: "Pro-rata operational dividend for {$symbol} from batch {$sourceBatchRef}",
            idempotencyKey: $deterministicKey,
            entries: $entries,
            referenceType: 'rwa_assets',
            referenceId: (string) $asset->id,
        ));

        return [
            'asset_code' => $symbol,
            'distributed_amount_idr' => $totalAllocated,
            'dust_reserved_idr' => $dust,
            'recipients_count' => count($holdings),
        ];
    }
}
