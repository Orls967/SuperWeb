<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;
use Modules\Hospital\Application\DTOs\WaterfallBreakdownDTO;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\ClaimWaterfall;
use Modules\Insurance\Contracts\CashlessGuaranteeRequester;

/**
 * Layanan Domain Koordinasi Manfaat Penjaminan Bertingkat (Waterfall Coordination of Benefits - CoB).
 * Mengatur prioritas penjaminan:
 *   1. Penjamin Utama (BPJS Kesehatan / INA-CBGs)
 *   2. Penjamin Sekunder (Asuransi Swasta / Korporat via G7-K31)
 *   3. Penjamin Tersier / Pasien (Out-of-Pocket / Self-Pay)
 * Memastikan invariant konservasi nilai: Total Tagihan = Total Tertanggung + Tanggungan Pasien.
 */
class CoordinationOfBenefitsWaterfallService
{
    public function __construct(
        private readonly ?CashlessGuaranteeRequester $cashlessGuaranteeRequester = null
    ) {}

    /**
     * Mengeksekusi kalkulasi waterfall CoB untuk satu episode penagihan rumah sakit.
     *
     * @param  BillingEpisode  $episode  Episode billing yang sedang diajukan
     * @param  int  $bpjsEligibleTariffIdr  Tarif paket INA-CBGs BPJS yang disetujui
     * @param  string|null  $privateInsurancePolicyNo  Nomor polis asuransi swasta (jika ada)
     * @param  string|null  $guaranteeLetterNo  Nomor GL pra-otorisasi (jika ada)
     * @return WaterfallBreakdownDTO Rincian pembagian tanggungan antar layer
     */
    public function executeWaterfall(
        BillingEpisode $episode,
        int $bpjsEligibleTariffIdr,
        ?string $privateInsurancePolicyNo = null,
        ?string $guaranteeLetterNo = null
    ): WaterfallBreakdownDTO {
        $totalGross = $episode->total_charges_idr;
        if ($totalGross <= 0) {
            throw new DomainException("Total tagihan billing episode #{$episode->id} tidak valid ({$totalGross}).");
        }

        $remainingBilled = $totalGross;
        $layerDetails = [];

        // Hapus entri waterfall sebelumnya jika sedang re-adjudikasi
        $episode->waterfalls()->delete();

        // -------------------------------------------------------------
        // LAYER 1: Penjamin Utama (BPJS Kesehatan)
        // -------------------------------------------------------------
        $bpjsCovered = min($remainingBilled, max(0, $bpjsEligibleTariffIdr));
        $remainingBilled -= $bpjsCovered;

        $episode->waterfalls()->create([
            'step_order' => 1,
            'payer_type' => 'BPJS',
            'payer_reference' => 'BPJS-SEP-'.str_pad((string) $episode->encounter_id, 8, '0', STR_PAD_LEFT),
            'gross_billed_idr' => $totalGross,
            'deductions_idr' => 0,
            'covered_amount_idr' => $bpjsCovered,
            'remaining_balance_idr' => $remainingBilled,
            'status' => 'ADJUDICATED',
            'settled_at' => now(),
        ]);

        $layerDetails[] = [
            'layer' => 1,
            'payer' => 'BPJS Kesehatan',
            'covered_idr' => $bpjsCovered,
            'remaining_after_layer_idr' => $remainingBilled,
        ];

        // -------------------------------------------------------------
        // LAYER 2: Penjamin Sekunder (Asuransi Swasta via G7-K31)
        // -------------------------------------------------------------
        $privateInsCovered = 0;
        if ($remainingBilled > 0 && ! empty($privateInsurancePolicyNo) && $this->cashlessGuaranteeRequester !== null) {
            $effectiveGl = $guaranteeLetterNo ?? 'GL-AUTO-'.strtoupper(substr(md5($privateInsurancePolicyNo), 0, 8));

            // Ajukan settlement pelunasan discharge ke modul Asuransi
            $dischargeSettlement = $this->cashlessGuaranteeRequester->requestDischargeSettlement(
                guaranteeNumber: $effectiveGl,
                finalBilledAmountIdr: $remainingBilled,
                itemizedFolioSummary: ['non_covered_idr' => 0],
                dischargeSummary: ['episode_id' => $episode->id]
            );

            $approvedClaim = (int) ($dischargeSettlement['approved_claim_idr'] ?? 0);
            $copayAmount = (int) ($dischargeSettlement['copay_amount_idr'] ?? 0);

            // Bersih yang ditanggung asuransi = Klaim yang disetujui minus copay
            $privateInsCovered = min($remainingBilled, max(0, $approvedClaim - $copayAmount));
            $remainingBilled -= $privateInsCovered;

            $episode->waterfalls()->create([
                'step_order' => 2,
                'payer_type' => 'PRIVATE_INSURANCE',
                'payer_reference' => $effectiveGl,
                'gross_billed_idr' => $remainingBilled + $privateInsCovered,
                'deductions_idr' => $copayAmount,
                'covered_amount_idr' => $privateInsCovered,
                'remaining_balance_idr' => $remainingBilled,
                'status' => 'SETTLED',
                'settled_at' => now(),
            ]);

            $layerDetails[] = [
                'layer' => 2,
                'payer' => 'Asuransi Swasta (K31)',
                'covered_idr' => $privateInsCovered,
                'copay_deducted_idr' => $copayAmount,
                'remaining_after_layer_idr' => $remainingBilled,
            ];
        }

        // -------------------------------------------------------------
        // LAYER 3: Out-of-Pocket Pasien (Self-Pay) & Alokasi Deposit Escrow
        // -------------------------------------------------------------
        $patientShare = max(0, $remainingBilled);
        $depositAvailable = max(0, $episode->escrow_deposit_idr);

        // Terapkan deposit pasien untuk melunasi sisa tagihan
        $depositApplied = min($patientShare, $depositAvailable);
        $patientFinalPayable = $patientShare - $depositApplied;
        $patientRefund = $depositAvailable - $depositApplied;

        $episode->waterfalls()->create([
            'step_order' => 3,
            'payer_type' => 'PATIENT_OOP',
            'payer_reference' => 'PATIENT-SELF-PAY',
            'gross_billed_idr' => $patientShare,
            'deductions_idr' => $depositApplied,
            'covered_amount_idr' => $patientShare,
            'remaining_balance_idr' => 0,
            'status' => $patientFinalPayable === 0 ? 'SETTLED' : 'PENDING',
            'settled_at' => $patientFinalPayable === 0 ? now() : null,
        ]);

        $layerDetails[] = [
            'layer' => 3,
            'payer' => 'Pasien (Out-of-Pocket)',
            'gross_patient_share_idr' => $patientShare,
            'deposit_applied_idr' => $depositApplied,
            'final_payable_idr' => $patientFinalPayable,
            'refund_idr' => $patientRefund,
        ];

        // Perbarui atribut ringkasan di BillingEpisode
        $episode->bpjs_coverage_idr = $bpjsCovered;
        $episode->insurance_copay_idr = $privateInsCovered;
        $episode->patient_share_idr = $patientShare;
        $episode->save();

        return new WaterfallBreakdownDTO(
            totalGrossChargesIdr: $totalGross,
            bpjsCoveredAmountIdr: $bpjsCovered,
            privateInsuranceCoveredAmountIdr: $privateInsCovered,
            patientOutOfPocketIdr: $patientShare,
            depositAppliedIdr: $depositApplied,
            patientRefundIdr: $patientRefund,
            patientFinalPayableIdr: $patientFinalPayable,
            layerDetails: $layerDetails,
            settlementStatus: $patientFinalPayable === 0 ? 'BALANCED' : 'OUTSTANDING_PAYABLE',
        );
    }
}
