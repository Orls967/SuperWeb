<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCenter extends Model
{
    use HasUuids;

    protected $table = 'mfg_work_centers';

    protected $fillable = [
        'plant_id', 'area_id', 'asset_id', 'code', 'name', 'kind',
        'capacity_per_hour', 'capacity_uom', 'efficiency_percent',
        'machine_cost_per_hour_idr', 'labor_cost_per_hour_idr', 'overhead_per_hour_idr',
        'is_active',
    ];

    protected $casts = [
        'capacity_per_hour' => 'integer', 'efficiency_percent' => 'integer',
        'machine_cost_per_hour_idr' => 'integer', 'labor_cost_per_hour_idr' => 'integer',
        'overhead_per_hour_idr' => 'integer', 'is_active' => 'boolean',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(PlantArea::class, 'area_id');
    }

    /** Biaya per jam terbobot efisiensi: mesin + tenaga + overhead (integer IDR). */
    public function totalCostPerHour(): int
    {
        $efficiency = max(1, (int) $this->efficiency_percent);
        $gross = (int) $this->machine_cost_per_hour_idr
            + (int) $this->labor_cost_per_hour_idr
            + (int) $this->overhead_per_hour_idr;

        // Efisiensi rendah → biaya/jam efektif naik (pembulatan ke atas, tetap integer).
        return (int) ceil($gross * 100 / $efficiency);
    }
}
