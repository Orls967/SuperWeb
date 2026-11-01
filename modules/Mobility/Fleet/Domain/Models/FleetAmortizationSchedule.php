<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FleetAmortizationSchedule extends Model
{
    protected $table = 'oto_fleet_amortization_schedules';

    protected $fillable = [
        'contract_id',
        'period_month',
        'principal_idr',
        'interest_idr',
        'payment_idr',
        'ending_liability_idr',
        'is_posted',
    ];

    protected $casts = [
        'period_month' => 'integer',
        'principal_idr' => 'integer',
        'interest_idr' => 'integer',
        'payment_idr' => 'integer',
        'ending_liability_idr' => 'integer',
        'is_posted' => 'boolean',
    ];
}
