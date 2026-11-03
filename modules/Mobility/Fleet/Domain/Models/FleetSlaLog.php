<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetSlaLog extends Model
{
    protected $table = 'oto_fleet_sla_logs';

    protected $fillable = [
        'contract_id',
        'vehicle_id',
        'incident_month',
        'telematics_grounded_hours',
        'workshop_service_hours',
        'total_downtime_hours',
        'breach_hours',
        'penalty_credit_idr',
        'is_compensated',
        'idempotency_key',
    ];

    protected $casts = [
        'telematics_grounded_hours' => 'integer',
        'workshop_service_hours' => 'integer',
        'total_downtime_hours' => 'integer',
        'breach_hours' => 'integer',
        'penalty_credit_idr' => 'integer',
        'is_compensated' => 'boolean',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(FleetContract::class, 'contract_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo('Modules\Core\Domain\Models\Vehicle', 'vehicle_id');
    }
}
