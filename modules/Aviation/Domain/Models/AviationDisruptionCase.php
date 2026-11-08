<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AviationDisruptionCase extends Model
{
    protected $table = 'avi_disruption_cases';

    protected $fillable = [
        'flight_id',
        'case_number',
        'incident_category',
        'delay_minutes',
        'status',
        'regulatory_category',
        'duty_of_care_required',
        'cash_compensation_required',
        'hotel_required',
        'incident_description',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'delay_minutes' => 'integer',
        'duty_of_care_required' => 'boolean',
        'cash_compensation_required' => 'boolean',
        'hotel_required' => 'boolean',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function flight(): BelongsTo
    {
        return $this->belongsTo(AviationFlight::class, 'flight_id');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(AviationDisruptionPassenger::class, 'disruption_case_id');
    }

    public function parametricClaims(): HasMany
    {
        return $this->hasMany(AviationParametricClaim::class, 'disruption_case_id');
    }

    public function isForceMajeure(): bool
    {
        return str_starts_with($this->incident_category, 'FORCE_MAJEURE') ||
            in_array($this->incident_category, ['ATC_RESTRICTION', 'AIRPORT_INFRASTRUCTURE'], true);
    }
}
