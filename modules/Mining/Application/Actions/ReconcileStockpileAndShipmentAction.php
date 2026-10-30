<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningReconciliationInvestigation;
use Modules\Mining\Domain\Models\MiningReconciliationRun;
use Modules\Shared\Application\BaseAction;

class ReconcileStockpileAndShipmentAction extends BaseAction
{
    /**
     * @return object{run: MiningReconciliationRun, investigation: ?MiningReconciliationInvestigation}
     */
    public function handle(
        string $siteId,
        string $periodStart,
        string $periodEnd,
        int $totalWeighedKg,
        int $totalStockpileInKg,
        int $totalStockpileOutKg,
        int $totalShippedKg,
        string $idempotencyKey = '',
        int $reconToleranceBps = 50
    ): object {
        if (empty(trim($idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency key wajib diisi.');
        }

        // Idempotency check
        $existingRun = MiningReconciliationRun::where('site_id', $siteId)
            ->where('run_uuid', $idempotencyKey)
            ->first();

        if ($existingRun) {
            $existingInvestigation = MiningReconciliationInvestigation::where('reconciliation_run_id', $existingRun->id)->first();

            return (object) [
                'run' => $existingRun,
                'investigation' => $existingInvestigation,
            ];
        }

        return DB::transaction(function () use (
            $siteId,
            $periodStart,
            $periodEnd,
            $totalWeighedKg,
            $totalStockpileInKg,
            $totalStockpileOutKg,
            $totalShippedKg,
            $idempotencyKey,
            $reconToleranceBps
        ) {
            $outflowDiff = abs($totalStockpileOutKg - $totalShippedKg);
            $varianceKg = ($outflowDiff > 0)
                ? $outflowDiff
                : abs($totalWeighedKg - $totalShippedKg);

            $varianceBps = $totalWeighedKg > 0
                ? (int) round(($varianceKg * 10000) / $totalWeighedKg)
                : 0;

            $status = ($varianceBps <= $reconToleranceBps)
                ? 'BALANCED'
                : 'DISCREPANCY_INVESTIGATION';

            $run = MiningReconciliationRun::create([
                'id' => (string) Str::uuid(),
                'run_uuid' => $idempotencyKey,
                'site_id' => $siteId,
                'period_start' => Carbon::parse($periodStart),
                'period_end' => Carbon::parse($periodEnd),
                'total_weighed_kg' => $totalWeighedKg,
                'total_stockpile_in_kg' => $totalStockpileInKg,
                'total_stockpile_out_kg' => $totalStockpileOutKg,
                'total_shipped_kg' => $totalShippedKg,
                'variance_kg' => $varianceKg,
                'variance_bps' => $varianceBps,
                'status' => $status,
            ]);

            $investigation = null;
            if ($status === 'DISCREPANCY_INVESTIGATION') {
                $code = sprintf('INV-%s-%s', date('ymd'), strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)));
                $investigation = MiningReconciliationInvestigation::create([
                    'id' => (string) Str::uuid(),
                    'reconciliation_run_id' => $run->id,
                    'investigation_code' => $code,
                    'status' => 'OPEN',
                    'resolution_notes' => null,
                    'resolved_by_user_id' => null,
                    'resolved_at' => null,
                ]);
            }

            return (object) [
                'run' => $run,
                'investigation' => $investigation,
            ];
        });
    }

    public function resolveInvestigation(
        string $investigationId,
        int $resolvedByUserId,
        string $resolutionNotes
    ): MiningReconciliationInvestigation {
        $investigation = MiningReconciliationInvestigation::findOrFail($investigationId);
        $investigation->status = 'RESOLVED';
        $investigation->resolved_by_user_id = $resolvedByUserId;
        $investigation->resolution_notes = $resolutionNotes;
        $investigation->resolved_at = Carbon::now();
        $investigation->save();

        return $investigation;
    }
}
