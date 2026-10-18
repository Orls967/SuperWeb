<?php

declare(strict_types=1);

namespace Modules\Fleet\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'present_value_liability_idr',
        'remaining_liability_idr',
        'accumulated_amortized_idr',
        'security_deposit_idr',
        'max_downtime_hours_per_month',
        'requires_four_eyes',
        'approved_by_primary_id',
        'approved_by_secondary_id',
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
        'present_value_liability_idr' => 'integer',
        'remaining_liability_idr' => 'integer',
        'accumulated_amortized_idr' => 'integer',
        'security_deposit_idr' => 'integer',
        'max_downtime_hours_per_month' => 'integer',
        'requires_four_eyes' => 'boolean',
        'approved_by_primary_id' => 'integer',
        'approved_by_secondary_id' => 'integer',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(FleetContractUnit::class, 'contract_id');
    }

    public function slaLogs(): HasMany
    {
        return $this->hasMany(FleetSlaLog::class, 'contract_id');
    }
}
