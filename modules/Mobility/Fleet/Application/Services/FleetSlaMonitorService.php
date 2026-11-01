<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Mobility\Fleet\Domain\Models\FleetContract;
use Modules\Mobility\Fleet\Domain\Models\FleetContractUnit;
use Modules\Mobility\Fleet\Domain\Models\FleetSlaLog;

class FleetSlaMonitorService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Evaluates monthly telematics + workshop downtime against SLA threshold.
     * Posts AR credit note if breach occurs and records to oto_fleet_sla_logs.
     */
    public function recordMonthlyDowntime(
        FleetContract $contract,
        int $vehicleId,
        string $incidentMonth, // YYYY-MM
        int $telematicsGroundedHours = 0,
        int $workshopServiceHours = 0
    ): FleetSlaLog {
        return DB::transaction(function () use ($contract, $vehicleId, $incidentMonth, $telematicsGroundedHours, $workshopServiceHours) {
            $totalDowntimeHours = $telematicsGroundedHours + $workshopServiceHours;
            $maxDowntime = $contract->max_downtime_hours_per_month;
            $breachHours = max(0, $totalDowntimeHours - $maxDowntime);
            $penaltyCreditIdr = $breachHours * 50000; // Rp 50.000 / breach hour

            $idempotencyKey = hash('sha256', "fleet:sla:{$contract->id}:{$vehicleId}:{$incidentMonth}:{$breachHours}");

            // Posting to ledger if penalty credit > 0
            $isCompensated = false;
            if ($penaltyCreditIdr > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'sla_compensation',
                    description: "Fleet SLA Breach Compensation {$incidentMonth} ({$breachHours}h breach)",
                    idempotencyKey: $idempotencyKey,
                    entries: [
                        PostingEntryDTO::forCode('expense:fleet:sla_penalties:IDR', 'IDR', -$penaltyCreditIdr),
                        PostingEntryDTO::forCode("ar:fleet:party:{$contract->party_id}:IDR", 'IDR', $penaltyCreditIdr),
                    ],
                    referenceType: 'fleet_contract',
                    referenceId: (string) $contract->id,
                ));
                $isCompensated = true;
            }

            // Record/update SLA log
            $slaLog = FleetSlaLog::updateOrCreate(
                [
                    'contract_id' => $contract->id,
                    'vehicle_id' => $vehicleId,
                    'incident_month' => $incidentMonth,
                ],
                [
                    'telematics_grounded_hours' => $telematicsGroundedHours,
                    'workshop_service_hours' => $workshopServiceHours,
                    'total_downtime_hours' => $totalDowntimeHours,
                    'breach_hours' => $breachHours,
                    'penalty_credit_idr' => $penaltyCreditIdr,
                    'is_compensated' => $isCompensated,
                    'idempotency_key' => $idempotencyKey,
                ]
            );

            // Sync downtime on unit
            $unit = FleetContractUnit::where('contract_id', $contract->id)
                ->where('vehicle_id', $vehicleId)
                ->first();

            if ($unit !== null) {
                $unit->downtime_hours_recorded += $totalDowntimeHours;
                $unit->save();
            }

            return $slaLog;
        });
    }
}
