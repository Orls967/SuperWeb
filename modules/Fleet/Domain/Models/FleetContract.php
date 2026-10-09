<?php

declare(strict_types=1);

namespace Modules\Fleet\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FleetContract extends Model
{
    protected $table = 'oto_fleet_contracts';

    protected $fillable = [
        'contract_number',
        'party_id',
        'duration_months',
        'total_units',
        'monthly_rental_idr',
        'total_lease_value_idr',
        'accumulated_amortized_idr',
        'max_downtime_hours_per_month',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_months' => 'integer',
        'total_units' => 'integer',
        'monthly_rental_idr' => 'integer',
        'total_lease_value_idr' => 'integer',
        'accumulated_amortized_idr' => 'integer',
        'max_downtime_hours_per_month' => 'integer',
    ];

    public function units()
    {
        return $this->hasMany(FleetContractUnit::class, 'contract_id');
    }
}
