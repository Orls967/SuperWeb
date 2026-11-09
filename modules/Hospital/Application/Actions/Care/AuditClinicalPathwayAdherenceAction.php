<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Carbon\CarbonImmutable;
use Modules\Hospital\Domain\Algorithms\ClinicalPathwayDelayDetector;
use Modules\Hospital\Domain\Models\Admission;

final class AuditClinicalPathwayAdherenceAction
{
    private ClinicalPathwayDelayDetector $detector;

    public function __construct(ClinicalPathwayDelayDetector $detector)
    {
        $this->detector = $detector;
    }

    /**
     * Memeriksa kepatuhan Clinical Pathway pada admisi rawat inap aktif.
     *
     * @param int $admissionId
     * @param list<array{code: string, day: int}> $plannedSteps
     * @param list<array{code: string, day: int}> $executedSteps
     * @param int $currentDay
     * @return array<string, mixed>
     */
    public function execute(int $admissionId, array $plannedSteps, array $executedSteps, int $currentDay): array
    {
        $admission = Admission::findOrFail($admissionId);

        $delayResult = $this->detector->detect(
            plannedSteps: $plannedSteps,
            executedSteps: $executedSteps,
            currentDay: $currentDay
        );

        $adherenceRate = count($plannedSteps) > 0 ? (count($executedSteps) / count($plannedSteps)) * 100 : 100.0;

        return [
            'admission_id' => $admission->id,
            'current_day' => $currentDay,
            'adherence_rate_pct' => round($adherenceRate, 2),
            'has_delay' => $delayResult->hasDelay,
            'delayed_steps' => $delayResult->delayedSteps,
            'variance_summary' => $delayResult->varianceSummary,
            'audited_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
