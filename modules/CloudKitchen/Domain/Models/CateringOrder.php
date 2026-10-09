<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CateringOrder extends Model
{
    protected $table = 'resto_ck_catering_orders';

    protected $fillable = [
        'order_code',
        'subscription_id',
        'kitchen_id',
        'delivery_date',
        'meal_slot',
        'status',
        'meal_cost_idr',
        'idempotency_key',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'meal_cost_idr' => 'integer',
    ];

    public function subscription()
    {
        return $this->belongsTo(CateringSubscription::class, 'subscription_id');
    }

    public function kitchen()
    {
        return $this->belongsTo(CloudKitchen::class, 'kitchen_id');
    }
}
