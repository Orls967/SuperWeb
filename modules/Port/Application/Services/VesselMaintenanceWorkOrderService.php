<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Asset\Contracts\MaintenanceWorkOrderRequester;
use Modules\Port\Domain\Models\FleetVessel;

/**
 * Service Pemeliharaan Kapal Berbasis Kondisi & Integrasi Asset Work Order.
 * Terintegrasi dengan modul Asset (MaintenanceWorkOrderRequester K9).
 */
final class VesselMaintenanceWorkOrderService
{
    public function __construct(
        private readonly ?MaintenanceWorkOrderRequester $woRequester = null
    ) {}

    /**
     * Jadwalkan inspeksi atau pemeliharaan terencana (Planned Maintenance System / PMS) kapal.
     *
     * @param int $vesselId
     * @param string $componentName
     * @param int $currentRunningHours
     * @param int $maintenanceIntervalHours
     * @param string $severity
     * @return array{
     *     vessel_id: int,
     *     component: string,
     *     hours_since_last_overhaul: int,
     *     is_due: bool,
     *     work_order_id: int|null,
     *     action_taken: string
     * }
     */
    public function evaluateComponentMaintenance(
        int $vesselId,
        string $componentName,
        int $currentRunningHours,
        int $maintenanceIntervalHours = 6000,
        string $severity = 'MEDIUM'
    ): array {
        $vessel = FleetVessel::findOrFail($vesselId);
        $isDue = ($currentRunningHours >= $maintenanceIntervalHours);
        $workOrderId = null;
        $actionTaken = 'NO_ACTION_REQUIRED';

        if ($isDue) {
            $actionTaken = "Jadwalkan overhaul berkala untuk {$componentName} di pelabuhan berikutnya.";

            if ($this->woRequester !== null) {
                // Request work order ke modul Asset
                $workOrderId = $this->woRequester->requestWorkOrder(
                    'FLEET_VESSEL_EQUIPMENT',
                    $vessel->id,
                    "Pemeliharaan terencana PMS: Overhaul {$componentName} setelah {$currentRunningHours} jam operasi pada kapal {$vessel->vessel_name}",
                    $severity,
                    CarbonImmutable::now()->addDays(7)
                );
            }
        }

        return [
            'vessel_id' => $vessel->id,
            'component' => $componentName,
            'hours_since_last_overhaul' => $currentRunningHours,
            'is_due' => $isDue,
            'work_order_id' => $workOrderId,
            'action_taken' => $actionTaken,
        ];
    }

    /**
     * Jadwalkan inspeksi lambung bawah air (Underwater Inspection in Lieu of Drydocking / UWILD).
     */
    public function requestUnderwaterHullInspection(
        int $vesselId,
        string $portName,
        CarbonImmutable $inspectionDate
    ): array {
        $vessel = FleetVessel::findOrFail($vesselId);

        $workOrderId = null;
        if ($this->woRequester !== null) {
            $workOrderId = $this->woRequester->requestWorkOrder(
                'FLEET_HULL_DIVER_INSPECTION',
                $vessel->id,
                "Inspeksi UWILD & pembersihan biofouling lambung kapal {$vessel->vessel_name} di {$portName}",
                'HIGH',
                $inspectionDate
            );
        }

        return [
            'vessel_id' => $vessel->id,
            'inspection_type' => 'UWILD_DIVER_SURVEY',
            'port_name' => $portName,
            'scheduled_date' => $inspectionDate->toDateString(),
            'work_order_id' => $workOrderId,
            'status' => 'SCHEDULED',
        ];
    }
}
