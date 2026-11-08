<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FleetVessel extends Model
{
    protected $table = 'prt_fleet_vessels';

    protected $fillable = [
        'imo_number',
        'vessel_name',
        'call_sign',
        'mmsi',
        'flag_state',
        'home_port',
        'vessel_type',
        'fleet_class_id',
        'built_year',
        'deadweight_tonnage',
        'gross_tonnage',
        'net_tonnage',
        'length_overall_cm',
        'beam_cm',
        'design_draft_cm',
        'scantling_draft_cm',
        'main_engine_power_kw',
        'design_speed_knots',
        'sfoc_design_grams_kwh',
        'fuel_types_supported',
        'cii_reference_a',
        'cii_reference_c',
        'operational_status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'design_speed_knots' => 'float',
        'sfoc_design_grams_kwh' => 'float',
        'cii_reference_a' => 'float',
        'cii_reference_c' => 'float',
    ];

    public function classification(): BelongsTo
    {
        return $this->belongsTo(FleetClass::class, 'fleet_class_id');
    }

    public function bunkerTanks(): HasMany
    {
        return $this->hasMany(FleetBunkerTank::class, 'vessel_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(FleetCertificate::class, 'vessel_id');
    }

    public function crewComplement(): HasMany
    {
        return $this->hasMany(FleetCrewComplement::class, 'vessel_id');
    }

    public function voyages(): HasMany
    {
        return $this->hasMany(FleetVoyage::class, 'vessel_id');
    }

    public function charterParties(): HasMany
    {
        return $this->hasMany(FleetCharterParty::class, 'vessel_id');
    }

    public function hasValidStatutoryCertificates(CarbonImmutable $at): bool
    {
        $certs = $this->certificates()->where('status', 'VALID')->get();
        if ($certs->isEmpty()) {
            return false;
        }

        foreach ($certs as $cert) {
            if ($cert->expiry_date && CarbonImmutable::parse($cert->expiry_date)->isBefore($at)) {
                return false;
            }
        }

        return true;
    }

    public function totalRobMetricTons(): float
    {
        return (float) $this->bunkerTanks()->sum('current_rob_metric_tons');
    }
}
