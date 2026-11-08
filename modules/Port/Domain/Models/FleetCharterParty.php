<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FleetCharterParty extends Model
{
    protected $table = 'prt_fleet_charter_parties';

    protected $fillable = [
        'fixture_number',
        'vessel_id',
        'charterer_name',
        'charter_type',
        'charter_form',
        'laycan_commencement',
        'laycan_cancellation',
        'freight_or_hire_rate',
        'currency',
        'laytime_allowed_hours',
        'laytime_terms',
        'demurrage_rate_daily',
        'despatch_rate_daily',
        'status',
        'special_clauses',
    ];

    protected $casts = [
        'laycan_commencement' => 'date',
        'laycan_cancellation' => 'date',
        'freight_or_hire_rate' => 'float',
        'laytime_allowed_hours' => 'float',
        'demurrage_rate_daily' => 'float',
        'despatch_rate_daily' => 'float',
        'special_clauses' => 'array',
    ];

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(FleetVessel::class, 'vessel_id');
    }

    public function freightContracts(): HasMany
    {
        return $this->hasMany(FleetFreightContract::class, 'charter_party_id');
    }

    public function voyages(): HasMany
    {
        return $this->hasMany(FleetVoyage::class, 'charter_party_id');
    }

    public function laytimeCalculations(): HasMany
    {
        return $this->hasMany(FleetLaytimeCalculation::class, 'charter_party_id');
    }

    public function offHireEvents(): HasMany
    {
        return $this->hasMany(FleetOffHireEvent::class, 'charter_party_id');
    }

    public function bunkerAdjustments(): HasMany
    {
        return $this->hasMany(FleetBunkerAdjustment::class, 'charter_party_id');
    }

    public function isTimeCharter(): bool
    {
        return $this->charter_type === 'TIME_CHARTER';
    }

    public function isVoyageCharter(): bool
    {
        return $this->charter_type === 'VOYAGE_CHARTER';
    }
}

