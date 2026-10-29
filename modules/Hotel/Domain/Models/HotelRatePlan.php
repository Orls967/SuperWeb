<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelRatePlan extends Model
{
    use HasFactory;

    protected $table = 'htl_rate_plans';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'property_id',
        'code',
        'name',
        'plan_type',
        'free_cancellation_hours',
        'penalty_nights',
        'requires_deposit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'free_cancellation_hours' => 'integer',
            'penalty_nights' => 'integer',
            'requires_deposit' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(HotelRateCalendar::class, 'rate_plan_id');
    }
}
