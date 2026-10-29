<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelRateCalendar extends Model
{
    use HasFactory;

    protected $table = 'htl_rate_calendar';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'property_id',
        'room_type_id',
        'rate_plan_id',
        'calendar_date',
        'rate_idr',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calendar_date' => 'date:Y-m-d',
            'rate_idr' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(HotelRatePlan::class, 'rate_plan_id');
    }
}
