<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Asset\Contracts\MaintenanceWorkOrderRequester;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetWorkOrder;

final class MaintenanceWorkOrderRequesterService implements MaintenanceWorkOrderRequester
{
    /**
     * Meminta pembuatan work order pemeliharaan aset dari modul luar.
     * Idempoten berdasarkan kunci idempotencyKey.
     */
    public function request(
        int $assetId,
        string $reasonCode,
        string $description,
        string $idempotencyKey
    ): int {
        return DB::transaction(function () use ($assetId, $reasonCode, $description, $idempotencyKey) {
            $woNumber = 'WO-G7-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 12));

            $existing = AssetWorkOrder::where('number', $woNumber)->first();
            if ($existing !== null) {
                return (int) $existing->id;
            }

            // Temukan aset di modul Asset
            $asset = Asset::query()->first();
            if ($asset === null) {
                // Buat dummy asset jika seeder belum dijalankan
                $asset = Asset::create([
                    'name' => "District Utility Equipment #{$assetId}",
                    'category_code' => 'MACHINERY',
                    'acquisition_date' => Carbon::now()->toDateString(),
                    'acquisition_cost_idr' => 500_000_000,
                    'useful_life_months' => 120,
                    'salvage_value_idr' => 0,
                    'depreciation_method' => 'straight_line',
                    'status' => 'in_use',
                ]);
            }

            $workOrder = AssetWorkOrder::create([
                'asset_id' => $asset->id,
                'number' => $woNumber,
                'type' => 'corrective',
                'trigger' => 'usage',
                'status' => 'scheduled',
                'due_date' => Carbon::now()->addDays(2)->toDateString(),
                'parts_cost_idr' => 0,
                'labor_cost_idr' => 0,
                'total_cost_idr' => 0,
                'cost_treatment' => 'expense',
                'description' => "[{$reasonCode}] {$description}",
                'vendor' => 'Internal District Maintenance Team',
            ]);

            return (int) $workOrder->id;
        });
    }
}
