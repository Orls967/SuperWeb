<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningBlendPlan;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\StockpileMovement;
use Modules\Shared\Application\BaseAction;

class ExecuteShipmentBlendAction extends BaseAction
{
    /**
     * @return object{plan: MiningBlendPlan, totalShippedKg: int}
     */
    public function handle(string $blendPlanId, string $idempotencyKey = ''): object
    {
        if (empty(trim($idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency key wajib diisi.');
        }

        return DB::transaction(function () use ($blendPlanId) {
            $plan = MiningBlendPlan::lockForUpdate()->findOrFail($blendPlanId);

            if ($plan->status === 'EXECUTED') {
                return (object) [
                    'plan' => $plan,
                    'totalShippedKg' => (int) $plan->allocated_kg,
                ];
            }

            $allocations = json_decode($plan->allocations_json, true) ?: [];
            $totalShipped = 0;

            foreach ($allocations as $stockpileId => $kg) {
                if ($kg <= 0) {
                    continue;
                }

                $stockpile = MiningStockpile::lockForUpdate()->findOrFail($stockpileId);
                $stockpile->deductMaterial((int) $kg);

                StockpileMovement::create([
                    'id' => (string) Str::uuid(),
                    'movement_uuid' => (string) Str::uuid(),
                    'site_id' => $plan->site_id,
                    'stockpile_id' => $stockpile->id,
                    'movement_type' => 'OUTFLOW_BLEND',
                    'direction' => 'OUT',
                    'weight_kg' => (int) $kg,
                    'reference_type' => 'BLEND_PLAN',
                    'reference_id' => $plan->id,
                ]);

                $totalShipped += (int) $kg;
            }

            $plan->status = 'EXECUTED';
            $plan->save();

            return (object) [
                'plan' => $plan,
                'totalShippedKg' => $totalShipped,
            ];
        });
    }
}
