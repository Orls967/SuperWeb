<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetOffHireEvent extends Model
{
    protected $table = 'prt_fleet_off_hire_events';

    protected $fillable = [
        'charter_party_id',
        'vessel_id',
        'incident_code',
        'reason_category',
        'off_hire_start_at',
        'off_hire_end_at',
        'duration_hours',
        'daily_hire_deduction_rate',
        'total_hire_deducted_usd',
        'bunker_consumed_during_off_hire_mt',
        'bunker_reimbursement_usd',
        'status',
    ];

    protected $casts = [
        'off_hire_start_at' => 'datetime',
        'off_hire_end_at' => 'datetime',
        'duration_hours' => 'float',
        'daily_hire_deduction_rate' => 'float',
        'total_hire_deducted_usd' => 'float',
        'bunker_consumed_during_off_hire_mt' => 'float',
        'bunker_reimbursement_usd' => 'float',
    ];

    public function charterParty(): BelongsTo
    {
        return $this->belongsTo(FleetCharterParty::class, 'charter_party_id');
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(FleetVessel::class, 'vessel_id');
    }
}
