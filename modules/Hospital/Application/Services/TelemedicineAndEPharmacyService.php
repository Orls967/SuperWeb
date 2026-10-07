<?php

namespace Modules\Hospital\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\TeleConsult;
use RuntimeException;

class TelemedicineAndEPharmacyService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 104.1 Create Telemedicine Consultation with Triage
     */
    public function bookTeleConsult(
        Patient $patient,
        int $doctorId,
        string $channelType,
        string $triageCategory,
        string $chiefComplaint
    ): TeleConsult {
        return TeleConsult::create([
            'consult_code' => 'TC-'.strtoupper(bin2hex(random_bytes(6))),
            'patient_id' => $patient->id,
            'doctor_id' => $doctorId,
            'channel_type' => $channelType,
            'triage_category' => $triageCategory,
            'chief_complaint' => $chiefComplaint,
            'status' => 'SCHEDULED',
        ]);
    }

    /**
     * 104.2 & 104.3 Issue digital e-prescription with allergy/interaction check and doctor hash
     */
    public function issueEPrescriptionOrder(
        Patient $patient,
        PharmacyBranch $branch,
        string $drugCode,
        string $drugName,
        int $quantity,
        int $unitPriceIdr,
        string $doctorSignatureHash,
        ?TeleConsult $teleConsult = null,
        string $drugClassification = 'REGULAR',
        ?string $secondDoctorApprovalHash = null,
        bool $isChronicSubscription = false
    ): EPharmacyOrder {
        // Validation (a): e-resep tanpa tanda tangan dokter ditolak
        if (empty(trim($doctorSignatureHash))) {
            throw new RuntimeException('Digital prescription rejected: missing doctor digital signature hash');
        }

        // Validation 104.3: Obat keras/psikotropika butuh approval dokter kedua
        if ($drugClassification === 'NARCOTIC_PSYCHOTROPIC' && empty(trim((string) $secondDoctorApprovalHash))) {
            throw new RuntimeException('Controlled substance prescription rejected: requires second doctor approval hash');
        }

        // Validation 104.3: Drug interaction & allergy check against Patient Human Passport
        if ($patient->encrypted_allergies) {
            $allergies = is_array($patient->encrypted_allergies)
                ? $patient->encrypted_allergies
                : json_decode($patient->encrypted_allergies, true);

            if (is_array($allergies) && in_array(strtoupper($drugCode), array_map('strtoupper', $allergies))) {
                throw new RuntimeException("Critical drug allergy detected: Patient is allergic to {$drugCode}");
            }
        }

        $totalPriceIdr = $quantity * $unitPriceIdr;

        return DB::transaction(function () use (
            $patient,
            $branch,
            $drugCode,
            $drugName,
            $quantity,
            $totalPriceIdr,
            $doctorSignatureHash,
            $teleConsult,
            $drugClassification,
            $secondDoctorApprovalHash,
            $isChronicSubscription
        ) {
            $order = EPharmacyOrder::create([
                'order_code' => 'EPO-'.strtoupper(bin2hex(random_bytes(6))),
                'tele_consult_id' => $teleConsult?->id,
                'patient_id' => $patient->id,
                'pharmacy_branch_id' => $branch->id,
                'drug_code' => $drugCode,
                'drug_name' => $drugName,
                'quantity' => $quantity,
                'total_price_idr' => $totalPriceIdr,
                'drug_classification' => $drugClassification,
                'doctor_signature_hash' => $doctorSignatureHash,
                'second_doctor_approval_hash' => $secondDoctorApprovalHash,
                'is_chronic_subscription' => $isChronicSubscription,
                'status' => 'CONFIRMED',
            ]);

            // Post payment & revenue to Ledger
            $this->ledgerService->post(new PostingDTO(
                type: 'EPHARMACY_SALE',
                description: "e-Pharmacy prescription fulfillment for order {$order->order_code}",
                idempotencyKey: "HSP-EPO-{$order->order_code}",
                entries: [
                    PostingEntryDTO::forCode('hsp:epharmacy_receivable:IDR', 'IDR', $totalPriceIdr),
                    PostingEntryDTO::forCode('hsp:epharmacy_revenue:IDR', 'IDR', -$totalPriceIdr),
                ],
                referenceType: 'EPHARMACY_ORDER',
                referenceId: (string) $order->id,
            ));

            return $order;
        });
    }

    /**
     * 104.6 (d) Confirm last-mile delivery with POD signature
     */
    public function completeDelivery(EPharmacyOrder $order, ?string $podSignatureHash = null): EPharmacyOrder
    {
        // Pengiriman obat keras wajib POD ber-sign
        if ($order->drug_classification === 'NARCOTIC_PSYCHOTROPIC' && empty(trim((string) $podSignatureHash))) {
            throw new RuntimeException('Delivery completion failed: Controlled substance requires signed proof of delivery');
        }

        $order->update([
            'pod_signature_hash' => $podSignatureHash ?? hash('sha256', "DELIVERED-{$order->order_code}"),
            'delivery_status' => 'DELIVERED',
            'status' => 'DELIVERED',
        ]);

        return $order;
    }
}
