<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Asset\Contracts\MaintenanceWorkOrderRequester;
use Modules\Aviation\Domain\Enums\MaintenanceVisitStatus;
use Modules\Aviation\Domain\Models\AviationAircraft;
use Modules\Aviation\Domain\Models\AviationMaintenanceVisit;
use Modules\Aviation\Ledger\AviationLedgerPostingService;

class ScheduleMaintenanceVisitAction
{
    public function __construct(
        private readonly AviationLedgerPostingService $ledgerService,
        private readonly ?MaintenanceWorkOrderRequester $woRequester = null
    ) {}

    public function execute(
        int $aircraftId,
        string $visitType,
        CarbonImmutable $scheduledStart,
        CarbonImmutable $scheduledEnd,
        string $idempotencyKey,
        int $estimatedCostMinor = 2500000000
    ): AviationMaintenanceVisit {
        return DB::transaction(function () use ($aircraftId, $visitType, $scheduledStart, $scheduledEnd, $idempotencyKey, $estimatedCostMinor) {
            $aircraft = AviationAircraft::findOrFail($aircraftId);

            $woRef = null;
            if ($this->woRequester && $aircraft->asset_id) {
                try {
                    $woId = $this->woRequester->request(
                        (int) $aircraft->asset_id,
                        "AVI_{$visitType}",
                        "Perawatan berkala {$visitType} pesawat {$aircraft->registration_mark}",
                        $idempotencyKey
                    );
                    $woRef = "WO-AST-{$woId}";
                } catch (\Throwable) {
                    $woRef = "WO-LOCAL-{$aircraft->id}";
                }
            } else {
                $woRef = "WO-LOCAL-{$aircraft->id}-".time();
            }

            $visit = AviationMaintenanceVisit::create([
                'aircraft_id' => $aircraftId,
                'visit_type' => $visitType,
                'scheduled_start' => $scheduledStart->toDateTimeString(),
                'scheduled_end' => $scheduledEnd->toDateTimeString(),
                'work_order_ref' => $woRef,
                'status' => MaintenanceVisitStatus::PLANNED->value,
            ]);

            $visit->transitionTo(MaintenanceVisitStatus::PLANNED, 'MAINTENANCE_SCHEDULED');

            // Jurnal estimasi beban pemeliharaan
            $ledgerKey = "avi:maint:post:{$visit->id}:{$idempotencyKey}";
            $this->ledgerService->postMaintenanceExpense($visit, $estimatedCostMinor, $ledgerKey);

            return $visit;
        });
    }
}
