<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AquacultureHarvestOperation extends Model
{
    protected $table = 'mar_harvest_operations';

    protected $fillable = [
        'harvest_code',
        'batch_id',
        'harvested_at',
        'harvest_method',
        'slurry_ice_temperature_celsius',
        'gross_biomass_harvested_kg',
        'survival_rate_percentage',
        'final_fcr',
        'average_count_per_kg',
        'chain_of_custody_cert',
        'lead_technician_name',
        'status',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AquacultureBatch::class, 'batch_id');
    }

    public function exportLots(): HasMany
    {
        return $this->hasMany(AquacultureExportLot::class, 'harvest_operation_id');
    }
}
