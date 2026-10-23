<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Actions;

use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaDividend;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Shared\Application\BaseAction;

class DistributeProRataRwaRentalYieldAction extends BaseAction
{
    /**
     * Distribute rental income or operating yield pool pro-rata to all verified RWA token holders.
     *
     * @return array{dividend: RwaDividend, total_pool_idr: int, distributed_idr: int, rounding_reserve_idr: int, recipient_count: int}
     */
    public function handle(int $rwaAssetId, int $totalRevenuePoolIdr): array
    {
        return $this->transaction(function () use ($rwaAssetId, $totalRevenuePoolIdr) {
            $asset = RwaAsset::where('id', $rwaAssetId)->firstOrFail();
            $totalSupply = $asset->total_supply_tokens;

            if ($totalSupply <= 0) {
                throw new \DomainException("RwaAsset {$asset->token_symbol} has zero total supply.");
            }

            $holdings = RwaHolding::where('rwa_asset_id', $rwaAssetId)
                ->where('token_balance', '>', 0)
                ->get();

            $totalDistributedIdr = 0;
            $recipientCount = 0;

            foreach ($holdings as $holding) {
                $payoutIdr = (int) floor(($totalRevenuePoolIdr * $holding->token_balance) / $totalSupply);
                if ($payoutIdr > 0) {
                    $totalDistributedIdr += $payoutIdr;
                    $recipientCount++;
                }
            }

            $roundingReserveIdr = max(0, $totalRevenuePoolIdr - $totalDistributedIdr);
            $distributionDate = now()->toDateString();
            $idempotencyKey = hash('sha256', "rwa:dividend:{$rwaAssetId}:{$totalRevenuePoolIdr}:{$distributionDate}");

            $dividend = RwaDividend::create([
                'rwa_asset_id' => $rwaAssetId,
                'total_revenue_pool_idr' => $totalRevenuePoolIdr,
                'distributed_idr' => $totalDistributedIdr,
                'rounding_reserve_idr' => $roundingReserveIdr,
                'distribution_date' => $distributionDate,
                'status' => 'completed',
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->audit(
                action: 'rwa.dividend.distributed',
                auditable: $dividend,
                oldValues: null,
                newValues: [
                    'asset_id' => $rwaAssetId,
                    'total_pool_idr' => $totalRevenuePoolIdr,
                    'distributed_idr' => $totalDistributedIdr,
                    'rounding_reserve_idr' => $roundingReserveIdr,
                    'recipients' => $recipientCount,
                ],
                correlationId: "rwa_div_{$rwaAssetId}_{$distributionDate}",
                impactType: 'rwa_yield_distribution'
            );

            $this->outbox(
                eventType: 'RwaYieldDistributed',
                payload: [
                    'asset_id' => $rwaAssetId,
                    'token_symbol' => $asset->token_symbol,
                    'total_pool_idr' => $totalRevenuePoolIdr,
                    'distributed_idr' => $totalDistributedIdr,
                    'rounding_reserve_idr' => $roundingReserveIdr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'dividend' => $dividend,
                'total_pool_idr' => $totalRevenuePoolIdr,
                'distributed_idr' => $totalDistributedIdr,
                'rounding_reserve_idr' => $roundingReserveIdr,
                'recipient_count' => $recipientCount,
            ];
        });
    }
}
