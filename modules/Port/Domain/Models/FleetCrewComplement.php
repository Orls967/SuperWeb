<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetCrewComplement extends Model
{
    protected $table = 'prt_fleet_crew_complements';

    protected $fillable = [
        'vessel_id',
        'minimum_safe_manning_cert_no',
        'deck_officers_required',
        'engine_officers_required',
        'deck_ratings_required',
        'engine_ratings_required',
        'catering_crew_required',
        'total_crew_onboard',
        'stcw_compliant',
        'last_inspected_at',
    ];

    protected $casts = [
        'stcw_compliant' => 'boolean',
        'last_inspected_at' => 'date',
    ];

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(FleetVessel::class, 'vessel_id');
    }

    public function totalRequired(): int
    {
        return $this->deck_officers_required +
            $this->engine_officers_required +
            $this->deck_ratings_required +
            $this->engine_ratings_required +
            $this->catering_crew_required;
    }

    public function isAdequatelyManned(): bool
    {
        return $this->stcw_compliant && ($this->total_crew_onboard >= $this->totalRequired());
    }
}

