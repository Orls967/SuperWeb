<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaDividend;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Shared\Domain\Math\HamiltonApportionment;

class RwaDividendService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    public function declareDividend(
        int $assetId,
        string $period,
        int $declaredAmountIdr,
        string $idempotencyKey
    ): RwaDividend {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        if ($declaredAmountIdr <= 0) {
            throw new InvalidArgumentException('Declared dividend amount must be positive.');
        }

        $asset = RwaAsset::find($assetId);
        if (! $asset) {
            throw new DomainException("RWA asset with ID {$assetId} not found.");
        }
        $poolCode = "rwa:pool:{$asset->token_symbol}:IDR";
        $pool = LedgerAccount::where('code', $poolCode)->first();
        $poolBalance = (int) ($pool?->cached_balance ?? 0);

        if ($poolBalance < $declaredAmountIdr) {
            throw new DomainException("Insufficient ledger pool balance for {$poolCode}: available {$poolBalance}, declared {$declaredAmountIdr}.");
        }

        return RwaDividend::updateOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'rwa_asset_id' => $asset->id,
                'total_revenue_pool_idr' => $declaredAmountIdr,
                'distributed_idr' => 0,
                'rounding_reserve_idr' => 0,
                'distribution_date' => $this->simClock->now(),
                'status' => 'DECLARED',
            ]
        );
    }

    /**
     * @return array<int, int>
     */
    public function distributeDividend(int $assetId, string $period, string $idempotencyKey): array
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        return DB::transaction(function () use ($assetId) {
            $asset = RwaAsset::lockForUpdate()->findOrFail($assetId);
            $dividend = RwaDividend::where('rwa_asset_id', $asset->id)
                ->where('status', 'DECLARED')
                ->latest()
                ->lockForUpdate()
                ->firstOrFail();

            $holders = RwaHolding::where('rwa_asset_id', $asset->id)
                ->where('token_balance', '>', 0)
                ->orderBy('user_id', 'asc')
                ->lockForUpdate()
                ->get();

            if ($holders->isEmpty()) {
                throw new DomainException("No active token holders found for RWA asset {$asset->token_symbol}.");
            }

            $items = [];
            foreach ($holders as $holder) {
                $items[] = [
                    'key' => (string) $holder->user_id,
                    'weight' => (int) $holder->token_balance,
                ];
            }

            $hamiltonResult = HamiltonApportionment::apportion((int) $dividend->total_revenue_pool_idr, $items);
            $payouts = [];

            foreach ($holders as $holder) {
                $payout = (int) ($hamiltonResult->allocations[(string) $holder->user_id] ?? 0);
                $payouts[$holder->user_id] = $payout;

                // Credit investor cash wallet
                $user = User::findOrFail($holder->user_id);
                $wallet = $user->walletAccount('IDR');
                $wallet->cached_balance = (string) (((int) $wallet->cached_balance) + $payout);
                $wallet->save();
            }

            // Debit pool account
            $poolCode = "rwa:pool:{$asset->token_symbol}:IDR";
            $pool = LedgerAccount::where('code', $poolCode)->first();
            if ($pool) {
                $pool->cached_balance = (string) (((int) $pool->cached_balance) - (int) $dividend->total_revenue_pool_idr);
                $pool->save();
            }

            $dividend->distributed_idr = $dividend->total_revenue_pool_idr;
            $dividend->status = 'DISTRIBUTED';
            $dividend->save();

            return $payouts;
        });
    }
}
