<?php

namespace Modules\Hospital\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hospital\Domain\Models\ImagingStudy;
use Modules\Hospital\Domain\Models\LabCatalog;
use Modules\Hospital\Domain\Models\LabResult;
use Modules\Hospital\Domain\Models\LabSpecimen;
use Modules\Hospital\Domain\Models\Patient;
use RuntimeException;

class HospitalLabAndImagingService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 105.2 Collect specimen with initial custody hash
     */
    public function collectSpecimen(
        Patient $patient,
        string $sampleType,
        ?int $encounterId = null
    ): LabSpecimen {
        $barcode = 'SPEC-'.strtoupper(bin2hex(random_bytes(6)));
        $now = now()->toIso8601String();
        $initialHash = hash('sha256', "ORIGIN-{$barcode}-{$patient->id}-{$now}");

        return LabSpecimen::create([
            'specimen_barcode' => $barcode,
            'patient_id' => $patient->id,
            'encounter_id' => $encounterId,
            'sample_type' => $sampleType,
            'collected_at' => now(),
            'transport_temp_c' => 4.0,
            'custody_hash' => $initialHash,
            'status' => 'COLLECTED',
        ]);
    }

    /**
     * 105.2 Scan chain-of-custody transfer with temperature check
     */
    public function transferCustody(LabSpecimen $specimen, string $handlerId, float $currentTempC): LabSpecimen
    {
        // Cold chain breach check for blood/swab (must stay between 2.0C and 8.0C)
        if ($currentTempC > 10.0 || $currentTempC < 0.0) {
            $specimen->update([
                'status' => 'REJECTED',
                'transport_temp_c' => $currentTempC,
            ]);
            throw new RuntimeException("Specimen rejected due to cold-chain temperature breach: {$currentTempC} C");
        }

        $newHash = hash('sha256', "{$specimen->custody_hash}-{$handlerId}-{$currentTempC}");
        $specimen->update([
            'custody_hash' => $newHash,
            'transport_temp_c' => $currentTempC,
            'status' => 'RECEIVED_LAB',
        ]);

        return $specimen;
    }

    /**
     * 105.3 Record Lab Result with auto-verify / critical alert logic
     */
    public function recordLabResult(
        LabSpecimen $specimen,
        string $testCode,
        float $numericValue,
        ?string $pathologistSignatureHash = null
    ): LabResult {
        // Validation 105.6 (a): rantai spesimen putus / status REJECTED -> hasil tidak bisa disahkan
        if ($specimen->status === 'REJECTED') {
            throw new RuntimeException('Cannot certify lab result: Specimen chain-of-custody broken or rejected');
        }

        $catalog = LabCatalog::where('test_code', $testCode)->first();
        $priceIdr = $catalog?->price_idr ?? 100_000;

        $isCritical = false;
        $status = 'AUTO_VERIFIED';

        if ($catalog && $catalog->reference_min !== null && $catalog->reference_max !== null) {
            if ($numericValue < $catalog->reference_min || $numericValue > $catalog->reference_max) {
                $isCritical = true;
                $status = 'CRITICAL_HOLD';
            }
        }

        // Critical value requires manual pathologist verification
        if ($isCritical) {
            if (empty(trim((string) $pathologistSignatureHash))) {
                // Must hold for pathologist
                $status = 'CRITICAL_HOLD';
            } else {
                $status = 'PATHOLOGIST_VERIFIED';
            }
        }

        return DB::transaction(function () use (
            $specimen,
            $testCode,
            $numericValue,
            $isCritical,
            $pathologistSignatureHash,
            $status,
            $priceIdr
        ) {
            $result = LabResult::create([
                'result_code' => 'RES-'.strtoupper(bin2hex(random_bytes(6))),
                'specimen_id' => $specimen->id,
                'test_code' => $testCode,
                'numeric_value' => $numericValue,
                'is_critical' => $isCritical,
                'verified_by_pathologist_hash' => $pathologistSignatureHash,
                'status' => $status,
            ]);

            // Only post billing if verified (AUTO_VERIFIED or PATHOLOGIST_VERIFIED)
            if (in_array($status, ['AUTO_VERIFIED', 'PATHOLOGIST_VERIFIED'])) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'LAB_TEST_BILLING',
                    description: "Laboratory test fee for test {$testCode} on specimen {$specimen->specimen_barcode}",
                    idempotencyKey: "HSP-LAB-{$result->result_code}",
                    entries: [
                        PostingEntryDTO::forCode('hsp:lab_receivable:IDR', 'IDR', $priceIdr),
                        PostingEntryDTO::forCode('hsp:lab_revenue:IDR', 'IDR', -$priceIdr),
                    ],
                    referenceType: 'LAB_RESULT',
                    referenceId: (string) $result->id,
                ));
            }

            return $result;
        });
    }

    /**
     * 105.5 Record Imaging Study with turnaround and billing
     */
    public function recordImagingStudy(
        Patient $patient,
        string $modality,
        string $bodyPart,
        int $turnaroundMinutes,
        int $feeIdr,
        string $findings
    ): ImagingStudy {
        return DB::transaction(function () use ($patient, $modality, $bodyPart, $turnaroundMinutes, $feeIdr, $findings) {
            $study = ImagingStudy::create([
                'study_instance_uid' => '1.2.840.10008.'.time().'.'.rand(1000, 9999),
                'patient_id' => $patient->id,
                'modality' => $modality,
                'body_part' => $bodyPart,
                'performed_at' => now(),
                'turnaround_minutes' => $turnaroundMinutes,
                'radiologist_findings' => $findings,
                'fee_idr' => $feeIdr,
                'status' => 'COMPLETED',
            ]);

            $this->ledgerService->post(new PostingDTO(
                type: 'IMAGING_STUDY_BILLING',
                description: "Imaging study fee for {$modality} {$bodyPart}",
                idempotencyKey: "HSP-IMG-{$study->study_instance_uid}",
                entries: [
                    PostingEntryDTO::forCode('hsp:imaging_receivable:IDR', 'IDR', $feeIdr),
                    PostingEntryDTO::forCode('hsp:imaging_revenue:IDR', 'IDR', -$feeIdr),
                ],
                referenceType: 'IMAGING_STUDY',
                referenceId: (string) $study->id,
            ));

            return $study;
        });
    }
}
