<?php

declare(strict_types=1);

namespace Modules\Aviation\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Models\AviationLoyaltyAccount;
use Modules\Aviation\Domain\Models\AviationLoyaltyTransaction;

class UpgradePassengerLoyaltyTierAction
{
    /**
     * Naikkan status tier keanggotaan frequent flyer dengan audit trail.
     *
     * @return array{account_id: int, previous_tier: string, updated_tier: string, status: string}
     */
    public function execute(int $accountId, string $newTier, string $justification): array
    {
        return DB::transaction(function () use ($accountId, $newTier, $justification) {
            $account = AviationLoyaltyAccount::lockForUpdate()->findOrFail($accountId);

            $target = strtolower($newTier);
            if (! in_array($target, ['silver', 'gold', 'platinum'], true)) {
                throw new DomainException(sprintf('Tier target [%s] tidak valid untuk promosi elit.', $newTier));
            }

            $prevTier = $account->current_tier;
            $account->current_tier = $target;
            $account->tier_expiry_date = now()->addYear()->toDateString();
            $account->save();

            $ref = sprintf('TX-UPG-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -6)));
            AviationLoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'transaction_reference' => $ref,
                'transaction_type' => 'adjustment',
                'miles_delta' => 0,
                'tier_miles_delta' => 0,
                'running_balance' => $account->redeemable_miles,
                'nonce_claim_hash' => hash('sha256', $ref.$justification),
            ]);

            return [
                'account_id' => $account->id,
                'previous_tier' => $prevTier,
                'updated_tier' => $target,
                'status' => 'UPGRADED_SUCCESSFULLY',
            ];
        });
    }
}
