<?php

declare(strict_types=1);

namespace Modules\Aviation\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Algorithms\CodeshareMileageAccrualEngine;
use Modules\Aviation\Domain\Models\AviationLoyaltyAccount;
use Modules\Aviation\Domain\Models\AviationLoyaltyTransaction;
use Modules\Aviation\Domain\Models\AviationTicket;

class AccrueFrequentFlyerMilesAction
{
    public function __construct(
        protected CodeshareMileageAccrualEngine $engine
    ) {}

    /**
     * Akrualkan miles frequent flyer dengan verifikasi token nonce anti-ganda.
     *
     * @return array{account_id: int, miles_added: int, new_balance: int, nonce_hash: string}
     */
    public function execute(
        int $loyaltyAccountId,
        int $ticketId,
        int $couponNumber,
        string $flightDate,
        int $tpmDistance,
        string $cabinClass = 'economy_standard'
    ): array {
        return DB::transaction(function () use ($loyaltyAccountId, $ticketId, $couponNumber, $flightDate, $tpmDistance, $cabinClass) {
            $account = AviationLoyaltyAccount::lockForUpdate()->findOrFail($loyaltyAccountId);
            $ticket = AviationTicket::findOrFail($ticketId);

            $nonceHash = $this->engine->generateClaimNonceHash(
                $ticket->ticket_number,
                $couponNumber,
                $flightDate
            );

            // Periksa apakah token klaim ini sudah pernah dicatat
            $existing = AviationLoyaltyTransaction::where('nonce_claim_hash', $nonceHash)->first();
            if ($existing !== null) {
                throw new DomainException(sprintf(
                    'Klaim poin loyalitas tiket [%s] kupon [%d] tanggal [%s] telah terdaftar (Anti-double dip terpicu).',
                    $ticket->ticket_number,
                    $couponNumber,
                    $flightDate
                ));
            }

            $calc = $this->engine->calculateRevenueShareAndMiles(
                (int) $ticket->total_price_minor_unit,
                $tpmDistance,
                $cabinClass,
                $account->current_tier
            );

            $milesDelta = $calc['miles_accrued'];
            $newRedeemable = $account->redeemable_miles + $milesDelta;
            $newTierMiles = $account->tier_miles + $milesDelta;

            $account->redeemable_miles = $newRedeemable;
            $account->tier_miles = $newTierMiles;
            $account->save();

            $txRef = sprintf('TX-MIL-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -6)));

            AviationLoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'transaction_reference' => $txRef,
                'transaction_type' => 'flight_accrual',
                'miles_delta' => $milesDelta,
                'tier_miles_delta' => $milesDelta,
                'running_balance' => $newRedeemable,
                'flight_ticket_id' => $ticket->id,
                'nonce_claim_hash' => $nonceHash,
            ]);

            return [
                'account_id' => $account->id,
                'miles_added' => $milesDelta,
                'new_balance' => $newRedeemable,
                'nonce_hash' => $nonceHash,
            ];
        });
    }
}
