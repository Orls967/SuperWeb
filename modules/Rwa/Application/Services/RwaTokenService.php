<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaDividend;
use Modules\Rwa\Domain\Models\RwaHolding;

class RwaTokenService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    public function issueAssetToken(
        string $symbol,
        string $name,
        string $underlyingType,
        string $underlyingId,
        int $appraisalValueIdr,
        int $totalSupplyTokens
    ): RwaAsset {
        return DB::transaction(function () use ($symbol, $name, $underlyingType, $underlyingId, $appraisalValueIdr, $totalSupplyTokens) {
            $tokenPrice = (int) intdiv($appraisalValueIdr, $totalSupplyTokens);

            return RwaAsset::create([
                'token_symbol' => $symbol,
                'name' => $name,
                'underlying_asset_type' => $underlyingType,
                'underlying_asset_id' => $underlyingId,
                'appraisal_value_idr' => $appraisalValueIdr,
                'total_supply_tokens' => $totalSupplyTokens,
                'token_price_idr' => $tokenPrice,
                'status' => 'active',
            ]);
        });
    }

    public function allocateTokens(RwaAsset $asset, int $userId, int $amount): RwaHolding
    {
        return DB::transaction(function () use ($asset, $userId, $amount) {
            $currentAllocated = RwaHolding::where('rwa_asset_id', $asset->id)->sum('token_balance');
            if (($currentAllocated + $amount) > $asset->total_supply_tokens) {
                throw new \InvalidArgumentException('Allocation exceeds total supply of asset token.');
            }

            $holding = RwaHolding::firstOrNew([
                'rwa_asset_id' => $asset->id,
                'user_id' => $userId,
            ]);

            $holding->token_balance += $amount;
            $holding->save();

            return $holding;
        });
    }

    public function distributeDailyDividend(RwaAsset $asset, int $revenuePoolIdr, string $dateKey): RwaDividend
    {
        $idempotencyKey = "rwa:div:{$asset->id}:{$dateKey}";

        return DB::transaction(function () use ($asset, $revenuePoolIdr, $idempotencyKey) {
            $existing = RwaDividend::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $holdings = RwaHolding::where('rwa_asset_id', $asset->id)
                ->where('token_balance', '>', 0)
                ->get();

            $totalTokens = $asset->total_supply_tokens;
            $distributedTotal = 0;
            $postingEntries = [];

            foreach ($holdings as $holding) {
                // Pro-rata: intdiv(revenuePool * balance, totalTokens)
                $userPayout = (int) intdiv($revenuePoolIdr * $holding->token_balance, $totalTokens);
                if ($userPayout > 0) {
                    $distributedTotal += $userPayout;
                    $postingEntries[] = PostingEntryDTO::forCode("wallet:user:{$holding->user_id}:IDR", 'IDR', $userPayout);
                }
            }

            $roundingReserve = $revenuePoolIdr - $distributedTotal;
            if ($roundingReserve > 0) {
                $postingEntries[] = PostingEntryDTO::forCode('rwa:rounding_reserve:IDR', 'IDR', $roundingReserve);
            }

            // Pool debit
            $postingEntries[] = PostingEntryDTO::forCode("rwa:pool:{$asset->token_symbol}:IDR", 'IDR', -$revenuePoolIdr);

            // Post double-entry batch dividend
            $this->ledgerService->post(new PostingDTO(
                type: 'rwa_dividend',
                description: "RWA Daily Dividend for {$asset->token_symbol}",
                idempotencyKey: $idempotencyKey,
                entries: $postingEntries,
                referenceType: 'rwa_asset',
                referenceId: (string) $asset->id,
            ));

            return RwaDividend::create([
                'rwa_asset_id' => $asset->id,
                'total_revenue_pool_idr' => $revenuePoolIdr,
                'distributed_idr' => $distributedTotal,
                'rounding_reserve_idr' => $roundingReserve,
                'distribution_date' => $this->simClock->now(),
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    public function redeemTokens(RwaAsset $asset, int $userId, int $tokenCount): void
    {
        DB::transaction(function () use ($asset, $userId, $tokenCount) {
            $holding = RwaHolding::where('rwa_asset_id', $asset->id)
                ->where('user_id', $userId)
                ->firstOrFail();

            if ($holding->token_balance < $tokenCount) {
                throw new \InvalidArgumentException('Insufficient token balance for redemption.');
            }

            $holding->token_balance -= $tokenCount;
            $holding->save();

            $asset->total_supply_tokens -= $tokenCount;
            $asset->save();
        });
    }
}
