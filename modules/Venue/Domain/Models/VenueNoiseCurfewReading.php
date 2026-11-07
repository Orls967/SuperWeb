<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VenueNoiseCurfewReading extends Model
{
    public $timestamps = false;

    protected $table = 'ven_noise_curfew_readings';

    protected $guarded = ['id'];

    protected $casts = [
        'decibel_level_db' => 'integer',
        'is_curfew_breach' => 'boolean',
        'penalty_assessment_idr' => 'integer',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id');
    }
}
