<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationScheduleVersion extends Model
{
    protected $table = 'avi_schedule_versions';

    protected $fillable = [
        'airline_id',
        'season_code',
        'version_number',
        'status',
        'effective_from',
        'effective_until',
        'published_at',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
        'published_at' => 'datetime',
        'version_number' => 'integer',
    ];

    public function airline(): BelongsTo
    {
        return $this->belongsTo(AviationAirline::class, 'airline_id');
    }

    public function isPublished(): bool
    {
        return in_array($this->status, ['published', 'active'], true);
    }
}
