<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shared\Domain\Traits\HasUuid;

class EventSpace extends Model
{
    use HasUuid;

    protected $table = 'mall_event_spaces';

    protected $fillable = [
        'uuid',
        'property_id',
        'code',
        'name',
        'area_sqm',
        'daily_rate',
        'hourly_rate',
        'max_booths',
        'is_active',
        'description',
    ];

    protected $casts = [
        'area_sqm' => 'float',
        'daily_rate' => 'integer',
        'hourly_rate' => 'integer',
        'max_booths' => 'integer',
        'is_active' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(EventBooking::class, 'event_space_id');
    }
}
