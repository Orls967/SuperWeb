<?php

declare(strict_types=1);

namespace Modules\Wealth\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Shared\Domain\Math\PortfolioAsset;
use Modules\Shared\Domain\Math\PortfolioRebalancer;
use Modules\Wealth\Domain\Models\WmNavHistory;
use Modules\Wealth\Domain\Models\WmPortfolioHolding;
use Modules\Wealth\Domain\Models\WmProfile;
use Modules\Wealth\Ledger\WealthAccounts;

class PortfolioManagementService
{
    /** @var array<string, array{rebalanced: bool, orders_count: int}> */
    protected static array $rebalanceCache = [];

    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    public function recordNavUpdate(
        string $assetCode,
        int $navScale4,
        string $source,
        string $idempotencyKey
    ): WmNavHistory {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        return WmNavHistory::create([
            'asset_code' => $assetCode,
            'nav_date' => $this->simClock->now()->toDateString(),
            'nav_scale_4' => $navScale4,
            'source' => $source,
            'idempotency_key' => $idempotencyKey,
            'created_at' => $this->simClock->now(),
        ]);
    }

    public function revaluePortfolioMtm(int $profileId, string $idempotencyKey): void
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        DB::transaction(function () use ($profileId) {
            $holdings = WmPortfolioHolding::where('profile_id', $profileId)->lockForUpdate()->get();

            foreach ($holdings as $holding) {
                $latestNavRecord = WmNavHistory::where('asset_code', $holding->asset_code)
                    ->latest('id')
                    ->first();

                if ($latestNavRecord) {
                    $newNav = (int) $latestNavRecord->nav_scale_4;
                    $holding->current_nav = $newNav;
                    // Formula: units * current_nav / 10
                    $holding->current_market_value_idr = intdiv((int) $holding->units * $newNav, 10);
                    $holding->last_mtm_at = $this->simClock->now();
                    $holding->save();
                }
            }
        });
    }

    /**
     * @return array{rebalanced: bool, orders_count: int}
     */
    public function evaluateAndRebalance(int $profileId, string $idempotencyKey): array
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        if (isset(self::$rebalanceCache[$idempotencyKey])) {
            return self::$rebalanceCache[$idempotencyKey];
        }

        return DB::transaction(function () use ($profileId, $idempotencyKey) {
            $profile = WmProfile::lockForUpdate()->findOrFail($profileId);
            $holdings = WmPortfolioHolding::where('profile_id', $profileId)->lockForUpdate()->get();

            if ($holdings->isEmpty()) {
                $result = ['rebalanced' => false, 'orders_count' => 0];
                self::$rebalanceCache[$idempotencyKey] = $result;

                return $result;
            }

            $totalAum = (int) $holdings->sum('current_market_value_idr');
            if ($totalAum <= 0) {
                $result = ['rebalanced' => false, 'orders_count' => 0];
                self::$rebalanceCache[$idempotencyKey] = $result;

                return $result;
            }

            $driftBandBps = (int) ($profile->drift_band_bps ?? 500);
            $maxDriftObserved = 0;

            foreach ($holdings as $holding) {
                $targetBps = match ($holding->asset_code) {
                    'PU_INDEX' => (int) $profile->target_money_market_bps,
                    'BOND_INDEX' => (int) $profile->target_bonds_bps,
                    'RWA_INDEX' => (int) $profile->target_equity_rwa_bps,
                    default => 0,
                };

                $currentBps = intdiv((int) $holding->current_market_value_idr * 10000, $totalAum);
                $drift = abs($currentBps - $targetBps);
                if ($drift > $maxDriftObserved) {
                    $maxDriftObserved = $drift;
                }
            }

            if ($maxDriftObserved <= $driftBandBps) {
                $result = ['rebalanced' => false, 'orders_count' => 0];
                self::$rebalanceCache[$idempotencyKey] = $result;

                return $result;
            }

            // Exceeds band: rebalance
            $ordersCount = 0;
            foreach ($holdings as $holding) {
                $targetBps = match ($holding->asset_code) {
                    'PU_INDEX' => (int) $profile->target_money_market_bps,
                    'BOND_INDEX' => (int) $profile->target_bonds_bps,
                    'RWA_INDEX' => (int) $profile->target_equity_rwa_bps,
                    default => 0,
                };

                $targetValueIdr = intdiv($totalAum * $targetBps, 10000);
                $nav = max(1, (int) $holding->current_nav);
                $targetUnits = intdiv($targetValueIdr * 10, $nav);

                if ($targetUnits !== $holding->units) {
                    $holding->units = max(0, $targetUnits);
                    $holding->current_market_value_idr = intdiv($holding->units * $nav, 10);
                    $holding->save();
                    $ordersCount++;
                }
            }

            $result = ['rebalanced' => true, 'orders_count' => max(1, $ordersCount)];
            self::$rebalanceCache[$idempotencyKey] = $result;

            return $result;
        });
    }

    /**
     * @param  list<array{id: string, nav: int, target_bps: int, lot_size: int}>  $assets
     * @return array{lots_purchased: array<string, int>, remaining_cash_idr: int}
     */
    public function investCashWithTargetWeights(
        int $profileId,
        int $cashAmountIdr,
        array $assets,
        string $idempotencyKey
    ): array {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        $profile = WmProfile::findOrFail($profileId);
        $bandBps = (int) ($profile->drift_band_bps ?? 500);

        $portfolioAssets = [];
        foreach ($assets as $assetData) {
            $portfolioAssets[] = new PortfolioAsset(
                id: (string) $assetData['id'],
                units: 0,
                nav: (int) $assetData['nav'],
                lot: (int) $assetData['lot_size'],
                targetBps: (int) $assetData['target_bps'],
                bandBps: $bandBps,
            );
        }

        $rebalanceResult = PortfolioRebalancer::rebalance($cashAmountIdr, $portfolioAssets);

        $lotsPurchased = [];
        foreach ($assets as $a) {
            $lotsPurchased[$a['id']] = 0;
        }
        foreach ($rebalanceResult->orders as $order) {
            if ($order['action'] === 'BUY') {
                $lotsPurchased[$order['id']] = ($lotsPurchased[$order['id']] ?? 0) + $order['lots'];
            }
        }

        // Update holdings in DB for profile
        DB::transaction(function () use ($profileId, $lotsPurchased, $assets) {
            $assetNavMap = [];
            foreach ($assets as $a) {
                $assetNavMap[$a['id']] = (int) $a['nav'];
            }

            foreach ($lotsPurchased as $assetId => $lots) {
                if ($lots > 0) {
                    $nav = $assetNavMap[$assetId] ?? 10000;
                    $holding = WmPortfolioHolding::firstOrNew([
                        'profile_id' => $profileId,
                        'asset_code' => $assetId,
                    ]);
                    $holding->units = ((int) ($holding->units ?? 0)) + $lots;
                    $holding->current_nav = $nav;
                    $holding->current_market_value_idr = intdiv($holding->units * $nav, 10);
                    $holding->save();
                }
            }
        });

        return [
            'lots_purchased' => $lotsPurchased,
            'remaining_cash_idr' => $rebalanceResult->remainingCash,
        ];
    }

    /**
     * @return array{fee_amount_idr: int}
     */
    public function chargeMonthlyManagementFee(int $profileId, int $feeAnnualBps, string $idempotencyKey): array
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        return DB::transaction(function () use ($profileId, $feeAnnualBps) {
            $holdings = WmPortfolioHolding::where('profile_id', $profileId)->lockForUpdate()->get();
            $totalAum = (int) $holdings->sum('current_market_value_idr');

            $annualFee = intdiv($totalAum * $feeAnnualBps, 10000);
            $monthlyFee = intdiv($annualFee, 12);

            $feeAccount = WealthAccounts::managementFeeRevenue();
            $feeAccount->cached_balance = (string) (((int) $feeAccount->cached_balance) + $monthlyFee);
            $feeAccount->save();

            return ['fee_amount_idr' => $monthlyFee];
        });
    }
}
