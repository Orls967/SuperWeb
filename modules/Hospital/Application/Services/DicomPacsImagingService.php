<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Domain\Models\ImagingSeries;
use Modules\Hospital\Domain\Models\ImagingStudy;

class DicomPacsImagingService
{
    /**
     * Attach a new DICOM image series to an imaging study
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
     * Evaluate turnaround SLA based on clinical priority
     *
     * @return array{
     *     is_within_sla: bool,
     *     turnaround_minutes: int,
     *     sla_limit_minutes: int,
     *     status: string
     * }
     */
    public function evaluateTurnaroundSla(ImagingStudy $study, string $priority = 'ROUTINE'): array
    {
        $limit = $priority === 'STAT' ? 30 : 120;
        $isWithinSla = $study->turnaround_minutes <= $limit;

        return [
            'is_within_sla' => $isWithinSla,
            'turnaround_minutes' => $study->turnaround_minutes,
            'sla_limit_minutes' => $limit,
            'status' => $isWithinSla ? 'SLA_MET' : 'SLA_BREACHED',
        ];
    }
}
