<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Domain\Enums\TurnaroundPriority;
use Modules\Hospital\Domain\Models\ImagingSeries;
use Modules\Hospital\Domain\Models\ImagingStudy;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\StateMachines\ImagingStudyPacsStateMachine;

/**
 * Layanan Integrasi Radiologi DICOM PACS & Zero-Footprint Viewer (Fase 105).
 */
class DiagnosticImagingPacsIntegrationService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Merekam studi pencitraan radiologi baru dan memproses billing otomatis.
     */
    public function recordImagingStudy(
        Patient $patient,
        string $modality,
        string $bodyPart,
        int $turnaroundMinutes,
        int $feeIdr,
        ?string $findings = null,
        string $priority = 'ROUTINE',
        ?string $studyUid = null
    ): ImagingStudy {
        return DB::transaction(function () use (
            $patient,
            $modality,
            $bodyPart,
            $turnaroundMinutes,
            $feeIdr,
            $findings,
            $studyUid
        ) {
            $studyUid = $studyUid ?? '1.2.840.113619.2.55.3.'.now()->format('YmdHis').'.'.random_int(100, 999);

            $study = ImagingStudy::create([
                'study_instance_uid' => $studyUid,
                'patient_id' => $patient->id,
                'modality' => $modality,
                'body_part' => $bodyPart,
                'performed_at' => now(),
                'turnaround_minutes' => $turnaroundMinutes,
                'radiologist_findings' => $findings,
                'fee_idr' => $feeIdr,
                'status' => ImagingStudyPacsStateMachine::COMPLETED,
            ]);

            // Penjurnalan pendapatan radiologi jika fee > 0
            if ($feeIdr > 0) {
                $this->postImagingRevenue($patient, $study, $feeIdr);
            }

            return $study;
        });
    }

    /**
     * Menambahkan seri citra baru ke dalam studi radiologi.
     */
    public function createSeries(
        ImagingStudy $study,
        string $seriesUid,
        string $modality,
        int $sliceCount,
        string $storageUri
    ): ImagingSeries {
        return ImagingSeries::create([
            'study_id' => $study->id,
            'series_instance_uid' => $seriesUid,
            'modality' => $modality,
            'slice_count' => $sliceCount,
            'storage_uri' => $storageUri,
        ]);
    }

    /**
     * Mengevaluasi Turnaround Time SLA (STAT vs Routine).
     *
     * @return array{is_within_sla: bool, status: string, target_minutes: int, actual_minutes: int}
     */
    public function evaluateTurnaroundSla(ImagingStudy $study, string $priority): array
    {
        $priorityEnum = match (strtoupper($priority)) {
            'STAT' => TurnaroundPriority::STAT,
            'CITO' => TurnaroundPriority::CITO,
            default => TurnaroundPriority::ROUTINE,
        };

        $targetMinutes = $priorityEnum->maxTurnaroundMinutes();
        $actualMinutes = $study->turnaround_minutes;
        $isWithin = $actualMinutes <= $targetMinutes;

        return [
            'is_within_sla' => $isWithin,
            'status' => $isWithin ? 'SLA_MET' : 'SLA_BREACHED',
            'target_minutes' => $targetMinutes,
            'actual_minutes' => $actualMinutes,
        ];
    }

    /**
     * Menjurnal pendapatan radiologi secara double-entry berimbang.
     */
    protected function postImagingRevenue(Patient $patient, ImagingStudy $study, int $feeIdr): void
    {
        $this->ledgerService->post(new PostingDTO(
            type: 'IMAGING_STUDY_BILLING',
            description: "Imaging Revenue recognition: {$study->modality} {$study->body_part} for Patient MRN {$patient->mrn}",
            idempotencyKey: "HSP-IMG-{$study->study_instance_uid}",
            entries: Posting::lines()
                ->debit('hsp:imaging_receivable:IDR', $feeIdr)
                ->credit('hsp:imaging_revenue:IDR', $feeIdr)
                ->toEntries('IDR'),
            referenceType: 'IMAGING_STUDY',
            referenceId: (string) $study->id,
        ));
    }
}
