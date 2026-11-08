<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationYieldBucket extends Model
{
    protected $table = 'avi_yield_buckets';

    protected $fillable = [
        'flight_id',
        'bucket_code',
        'cabin_class',
        'base_price_minor_unit',
        'allocated_capacity',
        'sold_count',
        'protection_level',
        'booking_limit',
        'is_closed',
        'algorithm_certificate_hash',
    ];

    protected $casts = [
        'base_price_minor_unit' => 'integer',
        'allocated_capacity' => 'integer',
        'sold_count' => 'integer',
        'protection_level' => 'integer',
        'booking_limit' => 'integer',
        'is_closed' => 'boolean',
    ];

    public function flight(): BelongsTo
    {
        return $this->belongsTo(AviationFlight::class, 'flight_id');
    }

    public function availableSeats(): int
    {
        return max(0, $this->allocated_capacity - $this->sold_count);
    }
}
