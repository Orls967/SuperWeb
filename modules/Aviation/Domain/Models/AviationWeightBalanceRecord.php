<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationWeightBalanceRecord extends Model
{
    public $timestamps = false;
    protected $table = 'avi_weight_balance_records';

    protected $fillable = [
        'flight_id',
        'aircraft_id',
        'total_weight_kg',
        'total_moment',
        'cg_mm',
        'fuel_weight_kg',
        'fuel_arm_mm',
        'oew_kg',
        'oew_arm_mm',
        'crew_weight_kg',
        'crew_arm_mm',
        'pax_weight_kg',
        'pax_arm_mm',
        'cargo_weight_kg',
        'cargo_arm_mm',
        'is_within_envelope',
        'certificate_hash',
        'created_at',
    ];

    protected $casts = [
        'total_weight_kg' => 'integer',
        'total_moment' => 'integer',
        'cg_mm' => 'integer',
        'fuel_weight_kg' => 'integer',
        'fuel_arm_mm' => 'integer',
        'oew_kg' => 'integer',
        'oew_arm_mm' => 'integer',
        'crew_weight_kg' => 'integer',
        'crew_arm_mm' => 'integer',
        'pax_weight_kg' => 'integer',
        'pax_arm_mm' => 'integer',
        'cargo_weight_kg' => 'integer',
        'cargo_arm_mm' => 'integer',
        'is_within_envelope' => 'boolean',
    ];

    public function flight(): BelongsTo
    {
        return $this->belongsTo(AviationFlight::class, 'flight_id');
    }

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(AviationAircraft::class, 'aircraft_id');
    }
}

