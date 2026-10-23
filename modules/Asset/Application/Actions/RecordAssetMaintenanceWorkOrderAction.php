<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetEvent;
use Modules\Asset\Domain\Models\AssetWorkOrder;
use Modules\Shared\Application\BaseAction;

class RecordAssetMaintenanceWorkOrderAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): AssetWorkOrder
    {
        return DB::transaction(function () use ($params) {
            $asset = Asset::findOrFail($params['asset_id']);
            $partsCost = (int) ($params['parts_cost_idr'] ?? 0);
            $laborCost = (int) ($params['labor_cost_idr'] ?? 0);
            $totalCost = $partsCost + $laborCost;
            $treatment = (string) ($params['cost_treatment'] ?? 'expense');
            $workOrderNumber = (string) ($params['number'] ?? 'WO-AST-'.strtoupper(Str::random(8)));

            // If capitalized, add to landed_cost_idr and recompute
            if ($treatment === 'capitalized' && $totalCost > 0) {
                $asset->increment('landed_cost_idr', $totalCost);
                $asset->recomputeBookValue();
                $asset->save();
            }

            $asset->update([
                'status' => AssetStatus::InUse,
                'condition' => (string) ($params['condition_after'] ?? 'good'),
            ]);

            $workOrder = AssetWorkOrder::create([
                'asset_id' => $asset->id,
                'number' => $workOrderNumber,
                'type' => (string) ($params['type'] ?? 'preventive'),
                'trigger' => (string) ($params['trigger'] ?? 'scheduled'),
                'status' => 'completed',
                'due_date' => $params['due_date'] ?? Carbon::now()->toDateString(),
                'completed_at' => Carbon::now()->toDateString(),
                'parts_cost_idr' => $partsCost,
                'labor_cost_idr' => $laborCost,
                'total_cost_idr' => $totalCost,
                'cost_treatment' => $treatment,
                'description' => (string) $params['description'],
                'vendor' => $params['vendor'] ?? null,
                'assigned_to_user_id' => isset($params['assigned_to_user_id']) ? (int) $params['assigned_to_user_id'] : null,
            ]);

            // Append AssetEvent
            $lastEvent = AssetEvent::where('asset_id', $asset->id)->orderByDesc('sequence')->first();
            $prevHash = $lastEvent?->hash ?? RegisterFixedAssetAction::GENESIS_HASH;
            $nextSeq = ($lastEvent?->sequence ?? 0) + 1;
            $nowIso = Carbon::now()->toIso8601String();

            $payload = [
                'action' => 'maintenance_completed',
                'work_order_number' => $workOrder->number,
                'total_cost_idr' => $totalCost,
                'treatment' => $treatment,
            ];
            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);

            $hash = AssetEvent::calculateHash($prevHash, $nextSeq, AssetEventType::Maintenance->value, $payloadJson, $nowIso);

            AssetEvent::create([
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'sequence' => $nextSeq,
                'event_type' => AssetEventType::Maintenance,
                'payload' => $payload,
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_by_name' => (string) ($params['recorded_by'] ?? 'Maintenance Supervisor'),
                'occurred_at' => $nowIso,
            ]);

            $this->audit(
                action: 'asset_maintenance_recorded',
                auditable: $workOrder,
                newValues: ['work_order' => $workOrder->number, 'total_cost' => $totalCost],
                impactType: 'asset_maintenance',
            );

            return $workOrder;
        });
    }
}
