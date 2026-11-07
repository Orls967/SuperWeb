<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HotelRatePlanVersion extends Model
{
    protected $table = 'htl_rate_plan_versions';

    protected $fillable = [
        'property_id',
        'room_type_id',
        'version_code',
        'business_date',
        'base_rate_idr',
        'calculated_bar_rate_idr',
        'floor_rate_idr',
        'ceiling_rate_idr',
        'occupancy_bps',
        'lead_bps',
        'event_bps',
        'is_event_boosted',
        'status',
        'approved_by_user_id',
        'published_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'business_date' => 'date',
        'base_rate_idr' => 'integer',
        'calculated_bar_rate_idr' => 'integer',
        'floor_rate_idr' => 'integer',
        'ceiling_rate_idr' => 'integer',
        'occupancy_bps' => 'integer',
        'lead_bps' => 'integer',
        'event_bps' => 'integer',
        'is_event_boosted' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
