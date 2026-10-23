<?php

declare(strict_types=1);

namespace Modules\Wealth\Application\Actions;

use Modules\Shared\Application\BaseAction;
use Modules\Wealth\Domain\Models\WmHolding;
use Modules\Wealth\Domain\Models\WmOrder;
use Modules\Wealth\Domain\Models\WmProfile;

class SweepCorporateIdleCashToOvernightYieldAction extends BaseAction
{
    /**
     * Sweep corporate operating idle cash above liquidity threshold into overnight yield instruments.
     *
     * @return array{profile_id: int, swept_amount_idr: int, new_holding_value_idr: int, order: WmOrder}
     */
    public function handle(
        int $profileId,
        int $availableCashIdr,
        int $operatingLiquidityBufferIdr = 20000000
    ): array {
        return $this->transaction(function () use (
            $profileId,
            $availableCashIdr,
            $operatingLiquidityBufferIdr
        ) {
            $profile = WmProfile::where('id', $profileId)->firstOrFail();

            $sweptAmountIdr = max(0, $availableCashIdr - $operatingLiquidityBufferIdr);
            if ($sweptAmountIdr <= 0) {
                throw new \DomainException("No surplus cash available for sweep. Available: IDR {$availableCashIdr}, Buffer: IDR {$operatingLiquidityBufferIdr}.");
            }

            $idempotencyKey = hash('sha256', "wm:sweep:{$profileId}:{$sweptAmountIdr}:".date('Ymd'));

            $order = WmOrder::firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'profile_id' => $profileId,
                    'asset_type' => 'mutual_funds',
                    'amount_idr' => $sweptAmountIdr,
                    'status' => 'executed',
                    'executed_at' => now(),
                ]
            );

            $holding = WmHolding::firstOrCreate(
                ['profile_id' => $profileId, 'asset_type' => 'mutual_funds'],
                ['value_idr' => 0]
            );
            $holding->value_idr += $sweptAmountIdr;
            $holding->save();

            $this->audit(
                action: 'wealth.cash.swept',
                auditable: $order,
                oldValues: null,
                newValues: [
                    'profile_id' => $profileId,
                    'swept_amount_idr' => $sweptAmountIdr,
                    'holding_value_idr' => $holding->value_idr,
                ],
                correlationId: "wm_sweep_{$profileId}_".date('Ymd'),
                impactType: 'corporate_treasury_sweep'
            );

            $this->outbox(
                eventType: 'CorporateIdleCashSwept',
                payload: [
                    'profile_id' => $profileId,
                    'swept_amount_idr' => $sweptAmountIdr,
                    'instrument' => 'overnight_money_market',
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'profile_id' => $profileId,
                'swept_amount_idr' => $sweptAmountIdr,
                'new_holding_value_idr' => $holding->value_idr,
                'order' => $order,
            ];
        });
    }
}
