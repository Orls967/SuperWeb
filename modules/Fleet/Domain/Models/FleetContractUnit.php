<?php

declare(strict_types=1);

namespace Modules\Fleet\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Models\Vehicle;

class FleetContractUnit extends Model
{
    protected $table = 'oto_fleet_contract_units';

    protected $fillable = [
        'contract_id',
        'vehicle_id',
        'baseline_odometer_km',
        'max_km_per_year',
        'downtime_hours_recorded',
        'status',
    ];

    protected $casts = [
        'baseline_odometer_km' => 'integer',
        'max_km_per_year' => 'integer',
        'downtime_hours_recorded' => 'integer',
    ];

    public function contract()
    {
        return $this->belongsTo(FleetContract::class, 'contract_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
