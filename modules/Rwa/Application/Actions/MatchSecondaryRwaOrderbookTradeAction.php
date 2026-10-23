<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Actions;

use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Rwa\Domain\Models\RwaOrderbookOrder;
use Modules\Shared\Application\BaseAction;

class MatchSecondaryRwaOrderbookTradeAction extends BaseAction
{
    /**
     * Match a buy order and a sell order on the secondary RWA orderbook.
     *
     * @return array{buy_order: RwaOrderbookOrder, sell_order: RwaOrderbookOrder, matched_qty: int, trade_price_idr: int, total_trade_value_idr: int}
     */
    public function handle(int $buyOrderId, int $sellOrderId): array
    {
        return $this->transaction(function () use ($buyOrderId, $sellOrderId) {
            $buyOrder = RwaOrderbookOrder::where('id', $buyOrderId)->lockForUpdate()->firstOrFail();
            $sellOrder = RwaOrderbookOrder::where('id', $sellOrderId)->lockForUpdate()->firstOrFail();

            if ($buyOrder->side !== 'BUY' || $sellOrder->side !== 'SELL') {
                throw new \DomainException('Invalid order sides for matching. Expected BUY and SELL.');
            }

            if ($buyOrder->rwa_asset_id !== $sellOrder->rwa_asset_id) {
                throw new \DomainException('Cannot match orders across different RWA assets.');
            }

            if ($buyOrder->price_per_token_idr < $sellOrder->price_per_token_idr) {
                throw new \DomainException('Buy price is below sell price. No trade possible.');
            }

            $buyRemaining = $buyOrder->quantity - $buyOrder->filled_quantity;
            $sellRemaining = $sellOrder->quantity - $sellOrder->filled_quantity;

            if ($buyRemaining <= 0 || $sellRemaining <= 0) {
                throw new \DomainException('One or both orders are already fully filled.');
            }

            $matchedQty = min($buyRemaining, $sellRemaining);
            $tradePriceIdr = $sellOrder->price_per_token_idr; // execution at seller limit price
            $totalTradeValueIdr = $matchedQty * $tradePriceIdr;

            // Transfer tokens from seller holding to buyer holding
            $sellerHolding = RwaHolding::where('rwa_asset_id', $sellOrder->rwa_asset_id)
                ->where('user_id', $sellOrder->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sellerHolding->token_balance < $matchedQty) {
                throw new \DomainException('Seller has insufficient unlocked token balance to fulfill trade.');
            }

            $sellerHolding->token_balance -= $matchedQty;
            $sellerHolding->save();

            $buyerHolding = RwaHolding::firstOrCreate(
                [
                    'rwa_asset_id' => $buyOrder->rwa_asset_id,
                    'user_id' => $buyOrder->user_id,
                ],
                [
                    'is_kyc_verified' => true,
                    'token_balance' => 0,
                    'locked_balance' => 0,
                ]
            );
            $buyerHolding->token_balance += $matchedQty;
            $buyerHolding->save();

            // Update order fills
            $buyOrder->filled_quantity += $matchedQty;
            $buyOrder->status = ($buyOrder->filled_quantity >= $buyOrder->quantity) ? 'FILLED' : 'PARTIALLY_FILLED';
            $buyOrder->save();

            $sellOrder->filled_quantity += $matchedQty;
            $sellOrder->status = ($sellOrder->filled_quantity >= $sellOrder->quantity) ? 'FILLED' : 'PARTIALLY_FILLED';
            $sellOrder->save();

            $idempotencyKey = hash('sha256', "rwa:match:{$buyOrderId}:{$sellOrderId}:{$matchedQty}:{$tradePriceIdr}");

            $this->audit(
                action: 'rwa.trade.matched',
                auditable: $buyOrder,
                oldValues: null,
                newValues: [
                    'buy_order_id' => $buyOrderId,
                    'sell_order_id' => $sellOrderId,
                    'matched_qty' => $matchedQty,
                    'trade_price_idr' => $tradePriceIdr,
                    'total_trade_value_idr' => $totalTradeValueIdr,
                ],
                correlationId: "rwa_trade_{$buyOrderId}_{$sellOrderId}",
                impactType: 'secondary_market_execution'
            );

            $this->outbox(
                eventType: 'RwaSecondaryTradeMatched',
                payload: [
                    'buy_order_id' => $buyOrderId,
                    'sell_order_id' => $sellOrderId,
                    'rwa_asset_id' => $buyOrder->rwa_asset_id,
                    'matched_qty' => $matchedQty,
                    'trade_price_idr' => $tradePriceIdr,
                    'total_trade_value_idr' => $totalTradeValueIdr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'buy_order' => $buyOrder,
                'sell_order' => $sellOrder,
                'matched_qty' => $matchedQty,
                'trade_price_idr' => $tradePriceIdr,
                'total_trade_value_idr' => $totalTradeValueIdr,
            ];
        });
    }
}
