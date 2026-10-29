<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelDailyRevenueSnapshot extends Model
{
    use HasUuids;

    protected $table = 'htl_daily_revenue_snapshots';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'property_id',
        'business_date',
        'total_rooms',
        'occupied_rooms',
        'out_of_order_rooms',
        'occupancy_rate_bps',
        'total_room_revenue_idr',
        'total_fnb_revenue_idr',
        'total_tax_pbjt_idr',
        'total_service_charge_idr',
        'adr_idr',
        'revpar_idr',
    ];

    protected $casts = [
        'business_date' => 'date',
        'total_rooms' => 'integer',
        'occupied_rooms' => 'integer',
        'out_of_order_rooms' => 'integer',
        'occupancy_rate_bps' => 'integer',
        'total_room_revenue_idr' => 'integer',
        'total_fnb_revenue_idr' => 'integer',
        'total_tax_pbjt_idr' => 'integer',
        'total_service_charge_idr' => 'integer',
        'adr_idr' => 'integer',
        'revpar_idr' => 'integer',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }
}
