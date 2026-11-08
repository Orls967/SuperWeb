<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Application\Sagas\LoyaltyMilesAccrualSaga;
use Modules\Aviation\Domain\Models\AviationLoyaltyAccount;
use Modules\Aviation\Domain\Models\AviationLoyaltyTransaction;

class AviationLoyaltyProgramService
{
    public function __construct(
        protected LoyaltyMilesAccrualSaga $accrualSaga
    ) {}

    /**
     * Daftarkan akun frequent flyer baru untuk penumpang.
     */
    public function enrollMember(int $airlineId, string|int $passengerPartyRef, string $initialTier = 'classic'): AviationLoyaltyAccount
    {
        $membershipNumber = sprintf('FFP-%d-%06d', $airlineId, rand(100000, 999999));

        return AviationLoyaltyAccount::create([
            'airline_id' => $airlineId,
            'passenger_party_ref' => (string) $passengerPartyRef,
            'membership_number' => $membershipNumber,
            'current_tier' => $initialTier,
            'tier_miles' => 0,
            'redeemable_miles' => 0,
            'status' => 'active',
            'tier_expiry_date' => now()->addYears(1)->toDateString(),
        ]);
    }

    /**
     * Akrualkan miles penerbangan secara terstruktur via Saga.
     */
    public function accrueFlightMiles(
        int $accountId,
        int $ticketId,
        int $couponNumber,
        string $flightDate,
        int $tpmDistance
    ): array {
        return $this->accrualSaga->execute(
            $accountId,
            $ticketId,
            $couponNumber,
            $flightDate,
            $tpmDistance
        );
    }

    /**
     * Penebusan poin miles untuk tiket penghargaan (Award Ticket).
     */
    public function redeemMiles(int $accountId, int $milesToRedeem, string $description): array
    {
        return DB::transaction(function () use ($accountId, $milesToRedeem, $description) {
            $account = AviationLoyaltyAccount::lockForUpdate()->findOrFail($accountId);

            if ($account->status !== 'active') {
                throw new DomainException(sprintf('Akun loyalitas [%s] tidak dalam status aktif.', $account->membership_number));
            }

            if ($account->redeemable_miles < $milesToRedeem) {
                throw new DomainException(sprintf(
                    'Saldo miles [%d] tidak mencukupi untuk penebusan [%d] miles.',
                    $account->redeemable_miles,
                    $milesToRedeem
                ));
            }

            $account->redeemable_miles -= $milesToRedeem;
            $account->save();

            $txRef = sprintf('TX-RED-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -6)));
            $nonceHash = hash('sha256', $txRef.$description.now()->toIso8601String());

            AviationLoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'transaction_reference' => $txRef,
                'transaction_type' => 'flight_redemption',
                'miles_delta' => -$milesToRedeem,
                'tier_miles_delta' => 0,
                'running_balance' => $account->redeemable_miles,
                'nonce_claim_hash' => $nonceHash,
            ]);

            return [
                'account_id' => $account->id,
                'miles_redeemed' => $milesToRedeem,
                'remaining_balance' => $account->redeemable_miles,
                'transaction_reference' => $txRef,
            ];
        });
    }
}
