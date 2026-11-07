<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableBottleOrder extends Model
{
    protected $table = 'ven_table_bottle_orders';

    protected $fillable = [
        'booking_id',
        'bottle_sku',
        'bottle_name',
        'quantity',
        'unit_price_idr',
        'total_price_idr',
        'spout_ml_dispensed',
        'variance_ml',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_idr' => 'integer',
        'total_price_idr' => 'integer',
        'spout_ml_dispensed' => 'integer',
        'variance_ml' => 'integer',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTableBooking::class, 'booking_id');
    }
}
