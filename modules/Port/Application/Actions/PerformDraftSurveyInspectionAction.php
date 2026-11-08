<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Application\Services\MaritimeCargoCustodyService;
use Modules\Port\Domain\Models\FleetPortCall;
use Modules\Port\Domain\Models\FleetVessel;

/**
 * Action Verifikasi Draft Survey Kargo & Toleransi Bill of Lading.
 */
final class PerformDraftSurveyInspectionAction
{
    public function __construct(
        private readonly MaritimeCargoCustodyService $custodyService
    ) {}

    public function execute(
        int $portCallId,
        float $draftFwdInit,
        float $draftAftInit,
        float $draftMidInit,
        float $draftFwdFin,
        float $draftAftFin,
        float $draftMidFin,
        float $billOfLadingDeclaredMt,
        float $tpc = 55.0,
        float $waterDensity = 1.025
    ): array {
        $portCall = FleetPortCall::with('voyage.vessel')->findOrFail($portCallId);

        $survey = $this->custodyService->calculateDraftSurvey(
            $draftFwdInit,
            $draftAftInit,
            $draftMidInit,
            $draftFwdFin,
            $draftAftFin,
            $draftMidFin,
            $waterDensity,
            $waterDensity,
            $tpc
        );

        $surveyCargoMt = $survey['net_cargo_loaded_mt'];
        $discrepancyMt = $surveyCargoMt - $billOfLadingDeclaredMt;
        $discrepancyPct = $billOfLadingDeclaredMt > 0 ? abs($discrepancyMt / $billOfLadingDeclaredMt) * 100.0 : 0.0;

        // Standar maritim: Perbedaan Draft Survey vs Shore Scale yang wajar adalah <= 0.50%
        $isWithinTolerance = ($discrepancyPct <= 0.50);
        $protestRequired = ! $isWithinTolerance;

        return [
            'port_call_id' => $portCall->id,
            'declared_bl_mt' => round($billOfLadingDeclaredMt, 2),
            'survey_cargo_mt' => $surveyCargoMt,
            'discrepancy_mt' => round($discrepancyMt, 2),
            'discrepancy_percentage' => round($discrepancyPct, 3),
            'is_within_tolerance' => $isWithinTolerance,
            'letter_of_protest_required' => $protestRequired,
            'recommendation' => $isWithinTolerance
                ? 'Selisih Draft Survey memenuhi batas toleransi maritim (<= 0.5%). Konosemen disetujui.'
                : 'Terdeteksi selisih bobot di luar batas toleransi! Nakhoda wajib menerbitkan Letter of Protest (LOP).',
        ];
    }
}
