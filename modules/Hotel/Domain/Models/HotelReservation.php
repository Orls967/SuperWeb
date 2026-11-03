<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HotelReservation extends Model
{
    protected $table = 'htl_reservations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'property_id',
        'room_type_id',
        'rate_plan_id',
        'room_id',
        'reservation_number',
        'guest_user_id',
        'check_in_date',
        'check_out_date',
        'daily_rate_idr',
        'total_room_charge_idr',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'daily_rate_idr' => 'integer',
        'total_room_charge_idr' => 'integer',
        'check_in_date' => 'date',
        'check_out_date' => 'date',
    ];

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

    public function room(): BelongsTo
    {
        return $this->belongsTo(HotelRoom::class, 'room_id');
    }

    public function folio(): HasOne
    {
        return $this->hasOne(HotelFolio::class, 'reservation_id');
    }

    public function roomNights(): HasMany
    {
        return $this->hasMany(HotelRoomNight::class, 'reservation_id');
    }
}
