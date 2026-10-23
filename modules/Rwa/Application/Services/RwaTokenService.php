<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaDividend;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Rwa\Domain\Models\RwaOffering;

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
        int $totalSupplyTokens,
        int $primaryApproverId = 1,
        int $secondaryApproverId = 2,
        string $appraisalDocRef = 'APPRAISAL-DOC-26.8'
    ): RwaAsset {
        if ($primaryApproverId === $secondaryApproverId) {
            throw new \InvalidArgumentException('Four-eyes approval requires two distinct approvers.');
        }

        if (empty($appraisalDocRef)) {
            throw new \InvalidArgumentException('Appraisal verification document is required for issuance.');
        }

        if ($totalSupplyTokens <= 0) {
            throw new \InvalidArgumentException('Total supply tokens must be greater than zero.');
        }

        $tokenPrice = (int) intdiv($appraisalValueIdr, $totalSupplyTokens);

        // Pembatasan total token = nilai appraisal
        if (($tokenPrice * $totalSupplyTokens) !== $appraisalValueIdr) {
            throw new \InvalidArgumentException('Total token multiplied by price must exactly equal appraisal value.');
        }

        return DB::transaction(function () use (
            $symbol,
            $name,
            $underlyingType,
            $underlyingId,
            $appraisalValueIdr,
            $totalSupplyTokens,
            $tokenPrice,
            $primaryApproverId,
            $secondaryApproverId,
            $appraisalDocRef
        ) {
            $now = $this->simClock->now();

            $asset = RwaAsset::create([
                'token_symbol' => $symbol,
                'name' => $name,
                'underlying_asset_type' => $underlyingType,
                'underlying_asset_id' => $underlyingId,
                'appraisal_document_ref' => $appraisalDocRef,
                'appraisal_value_idr' => $appraisalValueIdr,
                'total_supply_tokens' => $totalSupplyTokens,
                'token_price_idr' => $tokenPrice,
                'status' => 'approved',
                'approved_by_primary_id' => $primaryApproverId,
                'approved_by_secondary_id' => $secondaryApproverId,
                'approved_at' => $now,
            ]);

            // Penerbitan aset ledger dengan supply Σ = terbit
            $idempotencyKey = "rwa:issuance:{$asset->id}";
            $this->ledgerService->post(new PostingDTO(
                type: 'rwa_issuance',
                description: "Initial RWA Token Issuance for {$symbol}",
                idempotencyKey: $idempotencyKey,
                entries: [
                    PostingEntryDTO::forCode('rwa:issued_supply:default', 'IDR', -$appraisalValueIdr),
                    PostingEntryDTO::forCode('rwa:unsold:default', 'IDR', $appraisalValueIdr),
                ],
                referenceType: 'rwa_asset',
                referenceId: (string) $asset->id,
            ));

            return $asset;
        });
    }

    public function createOffering(
        RwaAsset $asset,
        int $tokensOffered,
        int $minLotSize = 1,
        int $durationDays = 14
    ): RwaOffering {
        return DB::transaction(function () use ($asset, $tokensOffered, $minLotSize, $durationDays) {
            if ($tokensOffered > $asset->total_supply_tokens) {
                throw new \InvalidArgumentException('Offering tokens cannot exceed total supply.');
            }

            $now = $this->simClock->now();
            $offeringCode = 'IPO-'.strtoupper(hash('crc32b', "{$asset->id}:{$tokensOffered}:".$now->timestamp));

            return RwaOffering::create([
                'rwa_asset_id' => $asset->id,
                'offering_code' => $offeringCode,
                'tokens_offered' => $tokensOffered,
                'tokens_sold' => 0,
                'price_per_token_idr' => $asset->token_price_idr,
                'min_lot_size' => $minLotSize,
                'lockup_days' => 30,
                'start_date' => $now,
                'end_date' => $now->copy()->addDays($durationDays),
                'status' => 'open',
            ]);
        });
    }

    public function allocateTokens(RwaAsset $asset, int $userId, int $amount): RwaHolding
    {
        return DB::transaction(function () use ($asset, $userId, $amount) {
            $currentAllocated = (int) RwaHolding::where('rwa_asset_id', $asset->id)->sum('token_balance');
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

        return DB::transaction(function () use ($asset, $revenuePoolIdr, $dateKey, $idempotencyKey) {
            $existing = RwaDividend::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $holdings = RwaHolding::where('rwa_asset_id', $asset->id)
                ->where('token_balance', '>', 0)
                ->get();

            $totalTokens = $asset->total_supply_tokens;
            if ($totalTokens <= 0) {
                throw new \InvalidArgumentException('Asset has no supply tokens.');
            }

            $distributedTotal = 0;
            $postingEntries = [];

            foreach ($holdings as $holding) {
                // Pro-rata: intdiv(revenuePool * balance, totalTokens)
                $userPayout = (int) intdiv($revenuePoolIdr * $holding->token_balance, $totalTokens);
                if ($userPayout > 0) {
                    $distributedTotal += $userPayout;
                    User::find($holding->user_id)?->walletAccount('IDR');
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
                'distribution_date' => Carbon::parse($dateKey),
                'status' => 'completed',
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

    public function auditRwa(): array
    {
        $assetCount = RwaAsset::count();
        $holdingCount = RwaHolding::count();
        $dividendCount = RwaDividend::count();
        $discrepancies = 0;
        $corruptDetails = [];

        $assets = RwaAsset::with('holdings')->get();
        foreach ($assets as $asset) {
            $totalAllocated = (int) $asset->holdings->sum('token_balance');
            if ($totalAllocated > $asset->total_supply_tokens) {
                $discrepancies++;
                $corruptDetails[] = "Asset ID {$asset->id} ({$asset->token_symbol}): holdings {$totalAllocated} exceeds supply {$asset->total_supply_tokens}";
            }
        }

        $dividends = RwaDividend::all();
        foreach ($dividends as $div) {
            if ($div->total_revenue_pool_idr !== ($div->distributed_idr + $div->rounding_reserve_idr)) {
                $discrepancies++;
                $corruptDetails[] = "Dividend ID {$div->id}: pool {$div->total_revenue_pool_idr} does not equal distributed {$div->distributed_idr} + reserve {$div->rounding_reserve_idr}";
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'asset_count' => $assetCount,
            'holding_count' => $holdingCount,
            'dividend_count' => $dividendCount,
            'discrepancies' => $discrepancies,
            'details' => $corruptDetails,
        ];
    }
}
