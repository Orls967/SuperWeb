<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Actions;

use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Rwa\Domain\Models\RwaOffering;
use Modules\Shared\Application\BaseAction;

class SubscribePrimaryRwaEscrowOfferingAction extends BaseAction
{
    /**
     * Subscribe to primary RWA token offering and hold tokens in escrow/holding.
     *
     * @return array{holding: RwaHolding, tokens_subscribed: int, total_cost_idr: int, lock_expires_at: ?string}
     */
    public function handle(
        int $offeringId,
        int $userId,
        int $quantityTokens
    ): array {
        return $this->transaction(function () use ($offeringId, $userId, $quantityTokens) {
            $offering = RwaOffering::where('id', $offeringId)->lockForUpdate()->firstOrFail();

            if ($offering->status !== 'active') {
                throw new \DomainException("Offering {$offering->offering_code} is {$offering->status} and cannot accept subscriptions.");
            }

            if ($quantityTokens < $offering->min_lot_size) {
                throw new \DomainException("Subscription minimum lot size is {$offering->min_lot_size} tokens.");
            }

            $remainingTokens = $offering->tokens_offered - $offering->tokens_sold;
            if ($quantityTokens > $remainingTokens) {
                throw new \DomainException("Insufficient tokens available in offering. Remaining: {$remainingTokens}.");
            }

            $totalCostIdr = $quantityTokens * $offering->price_per_token_idr;

            // Update offering progress
            $offering->tokens_sold += $quantityTokens;
            if ($offering->tokens_sold >= $offering->tokens_offered) {
                $offering->status = 'closed';
            }
            $offering->save();

            // Lockup expiry
            $lockExpiresAt = $offering->lockup_days > 0 ? now()->addDays($offering->lockup_days) : null;

            // Credit holding
            $holding = RwaHolding::firstOrCreate(
                [
                    'rwa_asset_id' => $offering->rwa_asset_id,
                    'user_id' => $userId,
                ],
                [
                    'is_kyc_verified' => true,
                    'token_balance' => 0,
                    'locked_balance' => 0,
                    'lock_expires_at' => $lockExpiresAt,
                ]
            );

            if ($offering->lockup_days > 0) {
                $holding->locked_balance += $quantityTokens;
                $holding->lock_expires_at = $lockExpiresAt;
            } else {
                $holding->token_balance += $quantityTokens;
            }
            $holding->save();

            $idempotencyKey = hash('sha256', "rwa:offering:sub:{$offeringId}:{$userId}:{$quantityTokens}:{$offering->tokens_sold}");

            $this->audit(
                action: 'rwa.offering.subscribed',
                auditable: $holding,
                oldValues: null,
                newValues: [
                    'offering_id' => $offeringId,
                    'user_id' => $userId,
                    'tokens' => $quantityTokens,
                    'total_cost_idr' => $totalCostIdr,
                ],
                correlationId: "rwa_sub_{$offeringId}_{$userId}",
                impactType: 'rwa_subscription'
            );

            $this->outbox(
                eventType: 'RwaOfferingSubscribed',
                payload: [
                    'offering_id' => $offeringId,
                    'user_id' => $userId,
                    'tokens_subscribed' => $quantityTokens,
                    'total_cost_idr' => $totalCostIdr,
                    'lock_expires_at' => $lockExpiresAt?->toIso8601String(),
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'holding' => $holding,
                'tokens_subscribed' => $quantityTokens,
                'total_cost_idr' => $totalCostIdr,
                'lock_expires_at' => $lockExpiresAt?->toIso8601String(),
            ];
        });
    }
}
