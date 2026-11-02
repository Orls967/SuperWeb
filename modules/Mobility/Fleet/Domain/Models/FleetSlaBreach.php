<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FleetSlaBreach extends Model
{
    protected $table = 'oto_fleet_sla_breaches';

    protected $fillable = [
        'breach_uuid',
        'contract_id',
        'unit_id',
        'incident_date',
        'downtime_hours',
        'breach_type',
        'penalty_bps',
        'penalty_amount_idr',
        'credited_invoice_id',
        'status',
    ];

    protected $casts = [
        'incident_date' => 'date',
        'downtime_hours' => 'integer',
        'penalty_bps' => 'integer',
        'penalty_amount_idr' => 'integer',
        'credited_invoice_id' => 'integer',
    ];

    public function contract()
    {
        return $this->belongsTo(FleetContract::class, 'contract_id');
    }
}
