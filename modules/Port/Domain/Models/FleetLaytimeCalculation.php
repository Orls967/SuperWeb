<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class FleetLaytimeCalculation extends Model
{
    protected $table = 'prt_fleet_laytime_calculations';

    protected $fillable = [
        'charter_party_id',
        'port_call_id',
        'calculation_reference',
        'nor_tendered_at',
        'laytime_commenced_at',
        'cargo_completed_at',
        'allowed_laytime_hours',
        'rain_interruption_hours',
        'breakdown_interruption_hours',
        'holiday_excluded_hours',
        'net_laytime_used_hours',
        'demurrage_hours',
        'despatch_hours',
        'daily_demurrage_rate',
        'calculated_amount_usd',
        'result_type',
        'settlement_status',
        'statement_of_facts',
    ];

    protected $casts = [
        'nor_tendered_at' => 'datetime',
        'laytime_commenced_at' => 'datetime',
        'cargo_completed_at' => 'datetime',
        'allowed_laytime_hours' => 'float',
        'rain_interruption_hours' => 'float',
        'breakdown_interruption_hours' => 'float',
        'holiday_excluded_hours' => 'float',
        'net_laytime_used_hours' => 'float',
        'demurrage_hours' => 'float',
        'despatch_hours' => 'float',
        'daily_demurrage_rate' => 'float',
        'calculated_amount_usd' => 'float',
        'statement_of_facts' => 'array',
    ];

    public function charterParty(): BelongsTo
    {
        return $this->belongsTo(FleetCharterParty::class, 'charter_party_id');
    }

    public function portCall(): BelongsTo
    {
        return $this->belongsTo(FleetPortCall::class, 'port_call_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(FleetDemurrageInvoice::class, 'laytime_calculation_id');
    }
}

