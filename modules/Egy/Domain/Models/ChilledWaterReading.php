<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ChilledWaterReading extends Model
{
    protected $table = 'egy_chilled_water_readings';

    protected $fillable = [
        'plant_id',
        'chiller_id',
        'supply_temp_c_milli',
        'return_temp_c_milli',
        'delta_temp_c_milli',
        'flow_rate_lps',
        'thermal_kwh_th',
        'electric_kwh',
        'cop_bps',
        'recorded_at',
    ];

    protected $casts = [
        'supply_temp_c_milli' => 'integer',
        'return_temp_c_milli' => 'integer',
        'delta_temp_c_milli' => 'integer',
        'flow_rate_lps' => 'integer',
        'thermal_kwh_th' => 'integer',
        'electric_kwh' => 'integer',
        'cop_bps' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(DistrictCoolingPlant::class, 'plant_id');
    }

    public function chiller(): BelongsTo
    {
        return $this->belongsTo(Chiller::class, 'chiller_id');
    }
}
