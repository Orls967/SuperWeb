<?php

namespace Modules\Hospital\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Models\FolioItem;
use Modules\Hospital\Domain\Models\MedFridgeBreach;
use Modules\Hospital\Domain\Models\Prescription;

class HospitalRevenueCycleService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 88.1 & 88.2 Open billing episode with surgical escrow deposit hold
     */
    public function openEpisodeWithDeposit(
        Encounter $encounter,
        int $escrowDepositIdr = 0
    ): BillingEpisode {
        return DB::transaction(function () use ($encounter, $escrowDepositIdr) {
            $episode = BillingEpisode::create([
                'episode_code' => 'EP-'.strtoupper(bin2hex(random_bytes(6))),
                'encounter_id' => $encounter->id,
                'escrow_deposit_idr' => $escrowDepositIdr,
                'total_charges_idr' => 0,
                'status' => 'OPEN',
            ]);

            if ($escrowDepositIdr > 0) {
                // Hold surgical escrow deposit in ledger
                $this->ledgerService->post(new PostingDTO(
                    type: 'HOSP_SURGICAL_ESCROW_DEPOSIT',
                    description: "Surgical escrow deposit for patient encounter {$encounter->encounter_code}",
                    idempotencyKey: "HSP-DEP-{$episode->episode_code}",
                    entries: [
                        PostingEntryDTO::forCode('hsp:patient_deposit_escrow:IDR', 'IDR', $escrowDepositIdr),
                        PostingEntryDTO::forCode('hsp:unearned_deposit:IDR', 'IDR', -$escrowDepositIdr),
                    ],
                    referenceType: 'HOSP_BILLING_EPISODE',
                    referenceId: (string) $episode->id,
                ));
            }

            return $episode;
        });
    }

    /**
     * Add charge item to episode folio
     */
    public function addFolioItem(
        BillingEpisode $episode,
        string $category,
        string $description,
        int $quantity,
        int $unitPriceIdr
    ): FolioItem {
        return DB::transaction(function () use ($episode, $category, $description, $quantity, $unitPriceIdr) {
            $subtotal = $quantity * $unitPriceIdr;

            $item = FolioItem::create([
                'billing_episode_id' => $episode->id,
                'item_category' => $category,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price_idr' => $unitPriceIdr,
                'subtotal_idr' => $subtotal,
            ]);

            $episode->increment('total_charges_idr', $subtotal);

            return $item;
        });
    }

    /**
     * 88.2 Finalize mixed-settlement billing upon discharge (BPJS coverage + Insurance Copay + Escrow Capture / Refund)
     */
    public function settleEpisodeDischarge(
        BillingEpisode $episode,
        int $bpjsCoverageIdr,
        int $insuranceCopayIdr
    ): BillingEpisode {
        return DB::transaction(function () use ($episode, $bpjsCoverageIdr, $insuranceCopayIdr) {
            $locked = BillingEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            $totalCharges = $locked->total_charges_idr;
            $thirdPartyPaid = $bpjsCoverageIdr + $insuranceCopayIdr;
            $patientShare = max(0, $totalCharges - $thirdPartyPaid);

            // Escrow reconciliation:
            $deposit = $locked->escrow_deposit_idr;
            $capturedDeposit = min($deposit, $patientShare);
            $refundRemainder = max(0, $deposit - $capturedDeposit);
            $remainingPatientDue = max(0, $patientShare - $capturedDeposit);

            $locked->update([
                'bpjs_coverage_idr' => $bpjsCoverageIdr,
                'insurance_copay_idr' => $insuranceCopayIdr,
                'patient_share_idr' => $patientShare,
                'status' => 'SETTLED',
            ]);

            // Post mixed settlement to ledger:
            // Debit BPJS Receivable, Debit Insurance Receivable, Debit Deposit Escrow Captured, Debit Patient Self-Pay
            // Credit Hospital Operating Revenue
            $entries = [
                PostingEntryDTO::forCode('hsp:hospital_revenue:IDR', 'IDR', -$totalCharges),
            ];

            if ($bpjsCoverageIdr > 0) {
                $entries[] = PostingEntryDTO::forCode('hsp:bpjs_receivable:IDR', 'IDR', $bpjsCoverageIdr);
            }
            if ($insuranceCopayIdr > 0) {
                $entries[] = PostingEntryDTO::forCode('hsp:insurance_receivable:IDR', 'IDR', $insuranceCopayIdr);
            }
            if ($capturedDeposit > 0) {
                $entries[] = PostingEntryDTO::forCode('hsp:patient_deposit_escrow:IDR', 'IDR', $capturedDeposit);
            }
            if ($remainingPatientDue > 0) {
                $entries[] = PostingEntryDTO::forCode('hsp:patient_self_pay:IDR', 'IDR', $remainingPatientDue);
            }

            // Refund deposit remainder if patient owed less than deposited
            if ($refundRemainder > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'HOSP_DEPOSIT_REFUND',
                    description: "Refund remainder surgical deposit for episode {$locked->episode_code}",
                    idempotencyKey: "HSP-REF-{$locked->episode_code}",
                    entries: [
                        PostingEntryDTO::forCode('hsp:unearned_deposit:IDR', 'IDR', $refundRemainder),
                        PostingEntryDTO::forCode('hsp:patient_deposit_escrow:IDR', 'IDR', -$refundRemainder),
                    ],
                    referenceType: 'HOSP_BILLING_EPISODE',
                    referenceId: (string) $locked->id,
                ));
            }

            $this->ledgerService->post(new PostingDTO(
                type: 'HOSP_EPISODE_SETTLEMENT',
                description: "Mixed settlement for hospital episode {$locked->episode_code}",
                idempotencyKey: "HSP-SET-{$locked->episode_code}",
                entries: $entries,
                referenceType: 'HOSP_BILLING_EPISODE',
                referenceId: (string) $locked->id,
            ));

            return $locked;
        });
    }

    /**
     * 88.3 e-Prescription with drug-drug contraindication detection
     */
    public function issuePrescription(
        Encounter $encounter,
        string $drugCode,
        string $drugName,
        int $qty,
        string $dosage,
        array $currentPatientDrugs = []
    ): Prescription {
        // Contraindication rule: e.g. WARFARIN + ASPIRIN
        $hasContraindication = false;
        if ($drugCode === 'ASPIRIN' && in_array('WARFARIN', $currentPatientDrugs)) {
            $hasContraindication = true;
        }

        return Prescription::create([
            'rx_code' => 'RX-'.strtoupper(bin2hex(random_bytes(6))),
            'encounter_id' => $encounter->id,
            'drug_code' => $drugCode,
            'drug_name' => $drugName,
            'qty_prescribed' => $qty,
            'dosage_instructions' => $dosage,
            'contraindication_alert' => $hasContraindication,
            'status' => 'PRESCRIBED',
        ]);
    }

    /**
     * 88.5 Medical cold-chain fridge breach: Quarantine lot and hold supplier payable in ledger
     */
    public function recordMedicalFridgeBreach(
        string $fridgeCode,
        string $lotNumber,
        float $recordedTempC,
        int $supplierId,
        int $supplierPayableIdr
    ): MedFridgeBreach {
        return DB::transaction(function () use ($fridgeCode, $lotNumber, $recordedTempC, $supplierId, $supplierPayableIdr) {
            $breachCode = 'MBR-'.strtoupper(bin2hex(random_bytes(6)));

            $breach = MedFridgeBreach::create([
                'breach_code' => $breachCode,
                'fridge_unit_code' => $fridgeCode,
                'lot_number' => $lotNumber,
                'recorded_temp_c' => $recordedTempC,
                'supplier_id' => $supplierId,
                'held_supplier_payable_idr' => $supplierPayableIdr,
                'status' => 'QUARANTINED',
            ]);

            // Hold supplier payable in ledger: Debit Supplier Payable, Credit Med Vaccine Dispute Escrow
            $this->ledgerService->post(new PostingDTO(
                type: 'HOSP_MED_FRIDGE_BREACH_HOLD',
                description: "Hold pharma supplier payable due to fridge temp breach lot {$lotNumber}",
                idempotencyKey: "HSP-FBD-{$breachCode}",
                entries: [
                    PostingEntryDTO::forCode('hsp:pharma_supplier_payable:IDR', 'IDR', $supplierPayableIdr),
                    PostingEntryDTO::forCode('hsp:vaccine_dispute_escrow:IDR', 'IDR', -$supplierPayableIdr),
                ],
                referenceType: 'MED_FRIDGE_BREACH',
                referenceId: (string) $breach->id,
            ));

            return $breach;
        });
    }
}
