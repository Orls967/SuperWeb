<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationSeatHold extends Model
{
    protected $table = 'avi_seat_holds';

    protected $fillable = [
        'flight_id',
        'cabin',
        'seats_count',
        'fare_minor',
        'currency',
        'expires_at',
        'idempotency_key',
        'is_confirmed',
        'is_released',
    ];

    protected $casts = [
        'seats_count' => 'integer',
        'fare_minor' => 'integer',
        'expires_at' => 'datetime',
        'is_confirmed' => 'boolean',
        'is_released' => 'boolean',
    ];

    public function flight(): BelongsTo
    {
        return $this->belongsTo(AviationFlight::class, 'flight_id');
    }

    public function isExpired(): bool
    {
        return CarbonImmutable::now()->isAfter($this->expires_at);
    }
}

