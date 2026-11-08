<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Enums\MaintenanceVisitStatus;
use Modules\Aviation\Domain\Models\AviationAircraft;
use Modules\Aviation\Domain\Models\AviationMaintenanceVisit;
use Modules\Aviation\Integrations\AssetMaintenanceIntegrationService;
use Modules\Aviation\Ledger\AviationAccounts;
use Modules\Aviation\Ledger\AviationLedgerPostingService;
use Modules\Aviation\Ledger\AviationTransactionTypes;

class AviationMaintenanceService
{
    public function __construct(
        private readonly AssetMaintenanceIntegrationService $assetIntegration,
        private readonly AviationLedgerPostingService $ledgerPostingService
    ) {}

    /**
     * Jadwalkan kunjungan perawatan armada pesawat, integrasi ke modul Asset, dan posting provisi beban.
     */
    public function scheduleMaintenance(array $payload): AviationMaintenanceVisit
    {
        $aircraft = AviationAircraft::findOrFail($payload['aircraft_id']);

        return DB::transaction(function () use ($payload, $aircraft) {
            $costMinor = (int) ($payload['cost_minor'] ?? 5000000000); // Rp 50.000.000 estimasi bawaan

            $visit = AviationMaintenanceVisit::create([
                'aircraft_id' => $aircraft->id,
                'check_type' => $payload['check_type'],
                'mro_station' => $payload['mro_station'] ?? 'CGK-GMF',
                'scheduled_start' => $payload['scheduled_start'],
                'scheduled_end' => $payload['scheduled_end'],
                'status' => MaintenanceVisitStatus::PLANNED->value,
                'cost_minor' => $costMinor,
                'currency' => 'IDR',
                'discrepancy_log' => $payload['discrepancy_log'] ?? null,
            ]);

            $visit->transitionTo(MaintenanceVisitStatus::PLANNED, 'MAINTENANCE_SCHEDULED');

            // Sinkronkan ke modul Asset (K9)
            $this->assetIntegration->syncWorkOrderToAssetModule($visit);

            // Jurnal Beban Pemeliharaan vs Hutang Usaha MRO
            $journalRef = 'POST-MNT-SCH-'.$visit->id;
            $this->ledgerPostingService->post(
                journalReference: $journalRef,
                transactionType: AviationTransactionTypes::AIRCRAFT_MAINTENANCE_EXPENSE_ACCRUED->value,
                amountMinor: $costMinor,
                debitAccount: AviationAccounts::MAINTENANCE_EXPENSE,
                creditAccount: AviationAccounts::MAINTENANCE_PAYABLE,
                description: "Provisi beban perawatan {$visit->check_type} pesawat {$aircraft->registration_mark}"
            );

            return $visit;
        });
    }

    /**
     * Selesaikan perawatan pesawat dan rilis kembali kelaikan terbangnya (Release to Service / RTS).
     */
    public function releaseToService(int $visitId, int $hoursReset = 0, int $cyclesReset = 0): AviationMaintenanceVisit
    {
        $visit = AviationMaintenanceVisit::findOrFail($visitId);
        $aircraft = AviationAircraft::findOrFail($visit->aircraft_id);

        return DB::transaction(function () use ($visit, $aircraft) {
            $visit->actual_end = now();
            $visit->transitionTo(MaintenanceVisitStatus::RELEASED_TO_SERVICE, 'MRO_INSPECTION_SIGNED_OFF');

            if ($visit->check_type === 'A_CHECK') {
                $aircraft->hours_since_last_a_check = 0;
            } elseif (in_array($visit->check_type, ['C_CHECK', 'D_CHECK'], true)) {
                $aircraft->cycles_since_last_c_check = 0;
                $aircraft->hours_since_last_a_check = 0;
            }

            $aircraft->airworthiness_expires_at = Carbon::parse($aircraft->airworthiness_expires_at)->addMonths(12)->toDateString();
            $aircraft->status = 'active';
            $aircraft->save();

            return $visit;
        });
    }
}
