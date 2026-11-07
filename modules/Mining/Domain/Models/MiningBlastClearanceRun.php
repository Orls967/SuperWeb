<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiningBlastClearanceRun extends Model
{
    use HasUuids;

    protected $table = 'min_blast_clearance_runs';

    protected $fillable = [
        'id',
        'blast_schedule_id',
        'run_code',
        'pattern_code',
        'pattern_id',
        'pit_name',
        'center_latitude',
        'center_longitude',
        'safety_radius_meters',
        'planned_holes_count',
        'total_explosives_kg',
        'personnel_detected_count',
        'equipment_detected_count',
        'all_personnel_evacuated',
        'siren_activated_at',
        'cleared_at',
        'detonated_at',
        'inspected_at',
        'officer_in_charge',
        'safety_notes',
        'clearance_notes',
        'abort_reason',
        'hmac_signature',
        'status',
        'metadata',
    ];

    protected $casts = [
        'center_latitude' => 'float',
        'center_longitude' => 'float',
        'safety_radius_meters' => 'float',
        'planned_holes_count' => 'integer',
        'total_explosives_kg' => 'float',
        'personnel_detected_count' => 'integer',
        'equipment_detected_count' => 'integer',
        'all_personnel_evacuated' => 'boolean',
        'siren_activated_at' => 'datetime',
        'cleared_at' => 'datetime',
        'detonated_at' => 'datetime',
        'inspected_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getPatternIdAttribute(): ?string
    {
        return $this->pattern_code;
    }

    public function setPatternIdAttribute(?string $value): void
    {
        $this->attributes['pattern_code'] = $value;
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(MiningBlastFragmentationEval::class, 'blast_clearance_run_id');
    }
}
