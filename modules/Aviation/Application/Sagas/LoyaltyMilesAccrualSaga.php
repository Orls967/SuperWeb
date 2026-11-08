<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Sagas;

use DomainException;
use Illuminate\Support\Facades\Log;
use Modules\Aviation\Actions\AccrueFrequentFlyerMilesAction;
use Modules\Aviation\Domain\Models\AviationLoyaltyAccount;

class LoyaltyMilesAccrualSaga
{
    public function __construct(
        protected AccrueFrequentFlyerMilesAction $accrueAction
    ) {}

    /**
     * Jalankan saga akrual miles loyalitas dengan jaminan rollback saldo.
     *
     * @return array{saga_id: string, status: string, account_id: int, miles_accrued: int}
     */
    public function execute(
        int $accountId,
        int $ticketId,
        int $couponNumber,
        string $flightDate,
        int $tpmDistance
    ): array {
        $sagaId = sprintf('SAGA-LOY-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -6)));
        $completedSteps = [];
        $addedMiles = 0;

        try {
            // Langkah 1: Kunci dan Verifikasi Status Akun Anggota
            $account = AviationLoyaltyAccount::findOrFail($accountId);
            if ($account->status !== 'active') {
                throw new DomainException(sprintf('Akun loyalitas [%s] sedang tidak aktif.', $account->membership_number));
            }
            $completedSteps[] = 'STEP_1_ACCOUNT_VERIFIED';

            // Langkah 2: Eksekusi Penambahan Saldo dan Pencatatan Mutasi
            $result = $this->accrueAction->execute(
                $accountId,
                $ticketId,
                $couponNumber,
                $flightDate,
                $tpmDistance
            );
            $addedMiles = $result['miles_added'];
            $completedSteps[] = 'STEP_2_MILES_ACCRUED';

            // Langkah 3: Evaluasi Kenaikan Tingkat Status (Tier Upgrade Evaluation)
            $this->evaluateTierUpgrade($account);
            $completedSteps[] = 'STEP_3_TIER_EVALUATED';

            return [
                'saga_id' => $sagaId,
                'status' => 'COMPLETED',
                'account_id' => $account->id,
                'miles_accrued' => $addedMiles,
            ];
        } catch (\Throwable $e) {
            Log::error('LoyaltyMilesAccrualSaga gagal, membatalkan transaksi', [
                'saga_id' => $sagaId,
                'error' => $e->getMessage(),
                'completed_steps' => $completedSteps,
            ]);

            $this->compensate($sagaId, $completedSteps, $accountId, $addedMiles);

            throw new DomainException(sprintf('Saga akrual miles gagal [%s]: %s', $sagaId, $e->getMessage()), 0, $e);
        }
    }

    protected function evaluateTierUpgrade(AviationLoyaltyAccount $account): void
    {
        $currentTier = $account->current_tier;
        $tierMiles = $account->tier_miles;

        $newTier = $currentTier;
        if ($tierMiles >= 65000) {
            $newTier = 'platinum';
        } elseif ($tierMiles >= 35000 && $currentTier !== 'platinum') {
            $newTier = 'gold';
        } elseif ($tierMiles >= 15000 && in_array($currentTier, ['classic', 'blue'], true)) {
            $newTier = 'silver';
        }

        if ($newTier !== $currentTier) {
            $account->current_tier = $newTier;
            $account->save();
        }
    }

    protected function compensate(string $sagaId, array $completedSteps, int $accountId, int $milesToDeduct): void
    {
        if (in_array('STEP_2_MILES_ACCRUED', $completedSteps, true) && $milesToDeduct > 0) {
            $account = AviationLoyaltyAccount::find($accountId);
            if ($account !== null) {
                $account->redeemable_miles = max(0, $account->redeemable_miles - $milesToDeduct);
                $account->tier_miles = max(0, $account->tier_miles - $milesToDeduct);
                $account->save();
            }
        }

        Log::info('Kompensasi LoyaltyMilesAccrualSaga selesai', [
            'saga_id' => $sagaId,
            'account_id' => $accountId,
            'deducted_miles' => $milesToDeduct,
        ]);
    }
}
