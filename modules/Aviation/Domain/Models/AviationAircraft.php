<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AviationAircraft extends Model
{
    protected $table = 'avi_aircraft';

    protected $fillable = [
        'asset_id',
        'registration_mark',
        'icao_type',
        'model_name',
        'operator_id',
        'cabin_y_capacity',
        'cabin_j_capacity',
        'mtow_kg',
        'oew_kg',
        'cg_forward_limit_mm',
        'cg_aft_limit_mm',
        'fuel_capacity_kg',
        'total_flight_minutes',
        'total_flight_cycles',
        'airworthiness_expires_at',
        'a_check_due_minutes',
        'a_check_current_minutes',
        'status',
    ];

    protected $casts = [
        'airworthiness_expires_at' => 'date',
        'cabin_y_capacity' => 'integer',
        'cabin_j_capacity' => 'integer',
        'mtow_kg' => 'integer',
        'oew_kg' => 'integer',
        'cg_forward_limit_mm' => 'integer',
        'cg_aft_limit_mm' => 'integer',
        'fuel_capacity_kg' => 'integer',
        'total_flight_minutes' => 'integer',
        'total_flight_cycles' => 'integer',
        'a_check_due_minutes' => 'integer',
        'a_check_current_minutes' => 'integer',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(AviationOperator::class, 'operator_id');
    }

    public function flights(): HasMany
    {
        return $this->hasMany(AviationFlight::class, 'aircraft_id');
    }

    public function maintenanceVisits(): HasMany
    {
        return $this->hasMany(AviationMaintenanceVisit::class, 'aircraft_id');
    }

    public function isAirworthyAt(CarbonImmutable $date): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $expiry = CarbonImmutable::parse($this->airworthiness_expires_at);
        return ! $expiry->startOfDay()->lessThan($date->startOfDay());
    }
}

