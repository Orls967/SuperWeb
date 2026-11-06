<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Egy\Domain\Models\DispatchOrder;
use Modules\Egy\Domain\Models\DispatchRun;
use Modules\Egy\Domain\Models\GenerationAsset;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\Algorithms\Energy\MeritOrderDispatch;

class RunHourlyDispatchAction extends BaseAction
{
    public function __construct(
        protected MeritOrderDispatch $dispatcher = new MeritOrderDispatch
    ) {}

    /**
     * @param  array{
     *     hour_timestamp: string,
     *     load_mw: int,
     *     idempotency_key?: string
     * }  $params
     */
    public function execute(array $params): DispatchRun
    {
        $hourTimestamp = Carbon::parse($params['hour_timestamp']);
        $loadMw = (int) $params['load_mw'];
        $idempotencyKey = $params['idempotency_key'] ?? "DISPATCH-{$hourTimestamp->format('YmdH')}";

        $existing = DispatchRun::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        // Fetch online generation assets
        $assets = GenerationAsset::whereIn('status', ['online', 'ONLINE'])->get();

        $units = [];
        foreach ($assets as $a) {
            $cap = (int) ($a->capacity_mw > 0 ? $a->capacity_mw : $a->installed_capacity_mw);
            $avail = (int) ($a->available_capacity_mw > 0 ? $a->available_capacity_mw : $cap);
            $minStable = (int) $a->min_stable_mw;
            $marginalCost = (int) ($a->marginal_cost_idr > 0 ? $a->marginal_cost_idr : $a->marginal_cost_per_mwh_minor);

            $units[] = [
                'id' => (string) $a->id,
                'capacity_mw' => $cap,
                'available_capacity_mw' => $avail,
                'min_stable_mw' => $minStable,
                'marginal_cost' => $marginalCost,
                'is_must_run' => (bool) $a->is_must_run,
            ];
        }

        // Run E2 MeritOrderDispatch
        $dispatchResult = $this->dispatcher->dispatch($units, $loadMw);

        return DB::transaction(function () use ($hourTimestamp, $loadMw, $dispatchResult, $idempotencyKey) {
            $runNumber = 'RUN-DISP-'.$hourTimestamp->format('Ymd-H').'-'.substr(md5($idempotencyKey), 0, 4);

            $run = DispatchRun::create([
                'run_number' => $runNumber,
                'hour_timestamp' => $hourTimestamp,
                'load_mw' => $loadMw,
                'total_dispatched_mw' => $dispatchResult->totalDispatchedMw,
                'total_curtailed_mw' => $dispatchResult->totalCurtailedMw,
                'total_cost_thousand_idr' => $dispatchResult->totalCostThousandIdr,
                'status' => 'dispatched',
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($dispatchResult->allocations as $alloc) {
                DispatchOrder::create([
                    'dispatch_run_id' => $run->id,
                    'generation_asset_id' => $alloc['id'],
                    'requested_mw' => $alloc['available_capacity_mw'],
                    'dispatched_mw' => $alloc['dispatched_mw'],
                    'curtailed_mw' => $alloc['curtailed_mw'],
                    'marginal_cost_idr' => $alloc['marginal_cost'],
                    'order_cost_thousand_idr' => $alloc['cost_thousand_idr'],
                    'status' => 'accepted',
                ]);

                // Update real-time output on generation asset
                GenerationAsset::where('id', $alloc['id'])->update([
                    'current_output_mw' => $alloc['dispatched_mw'],
                ]);
            }

            return $run;
        });
    }
}
