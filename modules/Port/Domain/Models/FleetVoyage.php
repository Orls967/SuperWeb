<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FleetVoyage extends Model
{
    protected $table = 'prt_fleet_voyages';

    protected $fillable = [
        'voyage_code',
        'vessel_id',
        'charter_party_id',
        'voyage_number',
        'voyage_type',
        'origin_port',
        'destination_port',
        'load_port',
        'discharge_port',
        'planned_start_at',
        'planned_end_at',
        'actual_start_at',
        'actual_end_at',
        'planned_distance_nm',
        'actual_distance_nm',
        'planned_speed_knots',
        'status',
        'operational_notes',
    ];

    protected $casts = [
        'planned_start_at' => 'datetime',
        'planned_end_at' => 'datetime',
        'actual_start_at' => 'datetime',
        'actual_end_at' => 'datetime',
        'planned_distance_nm' => 'float',
        'actual_distance_nm' => 'float',
        'planned_speed_knots' => 'float',
        'operational_notes' => 'array',
    ];

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(FleetVessel::class, 'vessel_id');
    }

    public function charterParty(): BelongsTo
    {
        return $this->belongsTo(FleetCharterParty::class, 'charter_party_id');
    }

    public function legs(): HasMany
    {
        return $this->hasMany(FleetVoyageLeg::class, 'voyage_id')->orderBy('leg_sequence');
    }

    public function noonReports(): HasMany
    {
        return $this->hasMany(FleetNoonReport::class, 'voyage_id')->orderBy('report_date');
    }

    public function passages(): HasMany
    {
        return $this->hasMany(FleetSeaPassage::class, 'voyage_id');
    }

    public function portCalls(): HasMany
    {
        return $this->hasMany(FleetPortCall::class, 'voyage_id');
    }

    public function weatherRoutings(): HasMany
    {
        return $this->hasMany(FleetWeatherRouting::class, 'voyage_id');
    }

    public function generalAverageCases(): HasMany
    {
        return $this->hasMany(FleetGeneralAverageCase::class, 'voyage_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(FleetLedgerEntry::class, 'voyage_id');
    }

    public function totalBunkerConsumedVlsfo(): float
    {
        return (float) $this->noonReports()->sum('vlsfo_consumed_24h_mt');
    }

    public function totalBunkerConsumedLsmgo(): float
    {
        return (float) $this->noonReports()->sum('lsmgo_consumed_24h_mt');
    }

    public function totalDistanceCovered(): float
    {
        return (float) $this->noonReports()->sum('distance_last_24h_nm');
    }
}
