<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Sagas;

use DomainException;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Application\DTOs\WaterfallBreakdownDTO;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Services\CoordinationOfBenefitsWaterfallService;
use Modules\Hospital\Domain\Services\HospitalAccountsPostingService;
use Modules\Hospital\Domain\Services\HospitalBillingCalculationService;
use Modules\Hospital\Domain\Services\InaCbgGroupingService;
use Modules\Hospital\Domain\StateMachines\BillingEpisodeStateMachine;

/**
 * Saga Komprehensif Rekonsiliasi & Pelunasan Billing Rumah Sakit (Hospital Billing Reconciliation Saga).
 * Mengoordinasikan alur bisnis multi-tahap:
 *   Langkah 1: Verifikasi kelengkapan folio & validasi matematis tagihan.
 *   Langkah 2: Adjudikasi klaim INA-CBGs BPJS & kalkulasi selisih kenaikan kelas rawat.
 *   Langkah 3: Eksekusi penjaminan bertingkat Waterfall Coordination of Benefits (BPJS -> Asuransi Swasta K31 -> Pasien).
 *   Langkah 4: Penyerapan deposit escrow & penentuan status pelunasan / refund.
 *   Langkah 5: Pembukuan double-entry ke akun buku besar standar HospitalAccounts (27 COA).
 */
class HospitalBillingReconciliationSaga
{
    public function __construct(
        private readonly HospitalBillingCalculationService $billingCalculationService,
        private readonly InaCbgGroupingService $inaCbgGroupingService,
        private readonly CoordinationOfBenefitsWaterfallService $waterfallService,
        private readonly HospitalAccountsPostingService $accountsPostingService
    ) {}

    /**
     * @param  array{
     *     billing_episode_id: int,
     *     primary_icd10: string,
     *     secondary_icd10?: array,
     *     procedures_icd9?: array,
     *     entitled_class: string,
     *     actual_class: string,
     *     private_insurance_policy_no?: ?string,
     *     guarantee_letter_no?: ?string
     * }  $params
     */
    public function execute(array $params): WaterfallBreakdownDTO
    {
        $episodeId = (int) $params['billing_episode_id'];
        $episode = BillingEpisode::with('items')->find($episodeId);

        if (! $episode) {
            throw new DomainException("Billing episode #{$episodeId} tidak ditemukan.");
        }

        DB::beginTransaction();

        try {
            // LANGKAH 1: Validasi Folio & Hitung Ulang Total
            $totalCharges = $this->billingCalculationService->recalculateEpisodeTotal($episode);
            if ($totalCharges <= 0) {
                throw new DomainException("Total tagihan episode #{$episodeId} tidak boleh nol atau negatif.");
            }

            if (! $this->billingCalculationService->verifyFolioIntegrity($episode)) {
                throw new DomainException("Integritas folio terganggu pada episode #{$episodeId}.");
            }

            BillingEpisodeStateMachine::transition($episode, BillingEpisodeStateMachine::STATUS_VERIFIED_CODER);

            // LANGKAH 2: Hitung INA-CBGs Tarif
            $cbgResult = $this->inaCbgGroupingService->groupAndCalculateTariff(
                primaryDiagnosisCode: $params['primary_icd10'],
                secondaryDiagnosisCodes: $params['secondary_icd10'] ?? [],
                procedureCodes: $params['procedures_icd9'] ?? [],
                entitledClass: $params['entitled_class'],
                actualClass: $params['actual_class']
            );

            // LANGKAH 3: Jalankan Waterfall CoB
            $waterfall = $this->waterfallService->executeWaterfall(
                episode: $episode,
                bpjsEligibleTariffIdr: $cbgResult->entitledClassTariffIdr,
                privateInsurancePolicyNo: $params['private_insurance_policy_no'] ?? null,
                guaranteeLetterNo: $params['guarantee_letter_no'] ?? null
            );

            // LANGKAH 4: Transisi Status Episode
            if ($waterfall->settlementStatus === 'BALANCED') {
                BillingEpisodeStateMachine::transition($episode, BillingEpisodeStateMachine::STATUS_SETTLED);
            } else {
                BillingEpisodeStateMachine::transition($episode, BillingEpisodeStateMachine::STATUS_WATERFALL_PENDING);
            }

            // LANGKAH 5: Posting ke Hospital Ledger Accounts
            $this->accountsPostingService->postWaterfallSettlement($episode, $waterfall);

            DB::commit();

            return $waterfall;
        } catch (Exception $e) {
            DB::rollBack();
            // Rollback kompensasi status jika perlu
            if ($episode->exists) {
                $episode->refresh();
                if ($episode->status !== BillingEpisodeStateMachine::STATUS_OPEN) {
                    $episode->status = BillingEpisodeStateMachine::STATUS_OPEN;
                    $episode->save();
                }
            }
            throw $e;
        }
    }
}
