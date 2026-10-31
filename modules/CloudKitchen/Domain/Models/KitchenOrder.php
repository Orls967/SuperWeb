<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class KitchenOrder extends Model
{
    protected $table = 'ckt_orders';

    protected $fillable = [
        'order_code',
        'subscription_id',
        'kitchen_id',
        'recipe_id',
        'delivery_date',
        'meal_slot',
        'status',
        'meal_cost_idr',
        'courier_batch_ref',
        'idempotency_key',
        'payload_hash',
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

    public function recipe()
    {
        return $this->belongsTo(KitchenRecipe::class, 'recipe_id');
    }

    public function consumptions()
    {
        return $this->hasMany(IngredientConsumption::class, 'order_id');
    }
}
