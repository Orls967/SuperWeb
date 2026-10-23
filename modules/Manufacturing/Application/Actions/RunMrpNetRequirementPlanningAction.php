<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Actions;

use InvalidArgumentException;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MrpRequirement;
use Modules\Manufacturing\Domain\Models\MrpRun;
use Modules\Manufacturing\Domain\Models\PlannedOrder;
use Modules\Shared\Application\BaseAction;

final class RunMrpNetRequirementPlanningAction extends BaseAction
{
    /**
     * Executes MRP Net Requirement Calculation for given demand items and generates planned orders.
     *
     * @param  list<array{material_id: int, gross_req: float, due_date: string}>  $demands
     */
    public function execute(string $runKey, array $demands, int $horizonDays = 30): MrpRun
    {
        if (empty($demands)) {
            throw new InvalidArgumentException('Daftar permintaan MRP tidak boleh kosong.');
        }

        return $this->transaction(function () use ($runKey, $demands, $horizonDays) {
            $mrpRun = MrpRun::create([
                'run_key' => $runKey,
                'status' => 'running',
                'is_scenario' => false,
                'horizon_days' => $horizonDays,
                'bucket_days' => 1,
                'started_at' => now(),
            ]);

            $totalPlannedOrders = 0;

            foreach ($demands as $demand) {
                $materialId = (string) $demand['material_id'];
                $grossReq = (float) $demand['gross_req'];
                $dueDate = $demand['due_date'];

                $material = Material::find($materialId);
                $onHand = 0.0; // In standard MRP netting, fetch on-hand balance

                $netReq = max(0.0, $grossReq - $onHand);

                MrpRequirement::create([
                    'run_id' => $mrpRun->id,
                    'material_id' => $materialId,
                    'period_start' => $dueDate,
                    'gross_req' => $grossReq,
                    'projected_on_hand' => $onHand,
                    'scheduled_receipts' => 0.0,
                    'planned_order_qty' => $netReq,
                ]);

                if ($netReq > 0) {
                    $kind = ($material !== null && $material->kind === 'raw') ? 'purchase' : 'production';

                    PlannedOrder::create([
                        'run_id' => $mrpRun->id,
                        'material_id' => $materialId,
                        'kind' => $kind,
                        'qty' => $netReq,
                        'release_date' => now()->toDateString(),
                        'due_date' => $dueDate,
                        'status' => 'suggested',
                    ]);

                    $totalPlannedOrders++;
                }
            }

            $mrpRun->update([
                'status' => 'completed',
                'finished_at' => now(),
                'summary' => [
                    'demands_processed' => count($demands),
                    'planned_orders_generated' => $totalPlannedOrders,
                ],
            ]);

            $this->audit('mfg.mrp_run_completed', $mrpRun, null, [
                'run_key' => $runKey,
                'planned_orders' => $totalPlannedOrders,
            ]);

            return $mrpRun->fresh(['requirements', 'plannedOrders']);
        });
    }
}
