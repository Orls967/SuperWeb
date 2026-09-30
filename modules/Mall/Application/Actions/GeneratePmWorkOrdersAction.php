<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mall\Domain\Enums\AssetStatus;
use Modules\Mall\Domain\Enums\WorkOrderPriority;
use Modules\Mall\Domain\Enums\WorkOrderStatus;
use Modules\Mall\Domain\Enums\WorkOrderType;
use Modules\Mall\Domain\Models\Asset;
use Modules\Mall\Domain\Models\WorkOrder;

class GeneratePmWorkOrdersAction
{
    /**
     * Generate Work Order Preventive Maintenance untuk seluruh aset mall yang jatuh tempo.
     *
     * @return Collection<int, WorkOrder>
     */
    public function execute(?Carbon $asOfDate = null): Collection
    {
        $asOfDate = $asOfDate ?? Carbon::today();

        $dueAssets = Asset::query()
            ->where('status', '!=', AssetStatus::DECOMMISSIONED)
            ->whereNotNull('next_pm_date')
            ->whereDate('next_pm_date', '<=', $asOfDate->toDateString())
            ->get();

        $generatedOrders = collect();

        DB::transaction(function () use ($dueAssets, $asOfDate, &$generatedOrders) {
            foreach ($dueAssets as $asset) {
                $orderNumber = 'WO-PM-'.$asOfDate->format('Ymd').'-'.strtoupper(Str::random(4));

                $workOrder = WorkOrder::create([
                    'property_id' => $asset->property_id,
                    'asset_id' => $asset->id,
                    'unit_id' => $asset->unit_id,
                    'tenant_id' => null,
                    'order_number' => $orderNumber,
                    'type' => WorkOrderType::PREVENTIVE,
                    'priority' => WorkOrderPriority::MEDIUM,
                    'title' => "Pemeliharaan Rutin: {$asset->name} ({$asset->asset_tag})",
                    'description' => "Pemeliharaan preventif terjadwal siklus {$asset->pm_frequency_days} hari.",
                    'status' => WorkOrderStatus::OPEN,
                    'due_date' => $asOfDate->copy()->addDays(2),
                    'sla_hours' => 48,
                    'sla_breached' => false,
                    'parts_cost' => 0,
                    'labor_cost' => 0,
                    'total_cost' => 0,
                    'is_billable_to_tenant' => false,
                ]);

                $generatedOrders->push($workOrder);

                $asset->update([
                    'last_pm_date' => $asOfDate->toDateString(),
                    'next_pm_date' => $asOfDate->copy()->addDays($asset->pm_frequency_days)->toDateString(),
                ]);
            }
        });

        return $generatedOrders;
    }
}
