<?php

declare(strict_types=1);

namespace Modules\Fleet\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Models\Vehicle;

class FleetContractUnit extends Model
{
    protected $table = 'oto_fleet_contract_units';

    protected $fillable = [
        'contract_id',
        'vehicle_id',
        'baseline_odometer_km',
        'current_odometer_km',
        'max_km_per_year',
        'downtime_hours_recorded',
        'condition_report_notes',
        'status',
    ];

    protected $casts = [
        'baseline_odometer_km' => 'integer',
        'current_odometer_km' => 'integer',
        'max_km_per_year' => 'integer',
        'downtime_hours_recorded' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(FleetContract::class, 'contract_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
