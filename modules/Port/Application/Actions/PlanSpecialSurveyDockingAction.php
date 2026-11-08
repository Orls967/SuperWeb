<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Application\Services\VesselDrydockingAndSurveyPlanningService;
use Modules\Port\Domain\Models\FleetVessel;

/**
 * Action Penjadwalan & Pengikatan Kontrak Drydocking Galangan Kapal.
 */
final class PlanSpecialSurveyDockingAction
{
    public function __construct(
        private readonly VesselDrydockingAndSurveyPlanningService $drydockService
    ) {}

    public function execute(
        int $vesselId,
        string $shipyardName,
        CarbonImmutable $dockingDate,
        int $durationDays = 20,
        float $steelRenewalTons = 20.0
    ): array {
        $vessel = FleetVessel::with('classification')->findOrFail($vesselId);

        if ($durationDays <= 0 || $durationDays > 60) {
            throw new DomainException('Durasi docking galangan kapal harus berada di rentang 1 sampai 60 hari.');
        }

        $plan = $this->drydockService->planDrydockSpecification(
            $vessel->id,
            $shipyardName,
            $dockingDate,
            $durationDays,
            $steelRenewalTons,
            4800.0,
            true // Special survey
        );

        // Perbarui tanggal survey di rekam jejak klasifikasi kapal
        if ($vessel->classification) {
            $vessel->classification->update([
                'last_drydock_survey' => $dockingDate->toDateString(),
                'next_drydock_survey' => $dockingDate->addMonths(30)->toDateString(),
            ]);
        }

        return [
            'vessel_id' => $vessel->id,
            'drydock_plan' => $plan,
            'planned_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
