<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Domain\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientConsumption extends Model
{
    public $timestamps = false;

    protected $table = 'ckt_ingredient_consumptions';

    protected $fillable = [
        'order_id',
        'ingredient_code',
        'quantity_consumed',
        'unit_cost_idr',
        'total_cogs_idr',
        'movement_idempotency_key',
        'created_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'quantity_consumed' => 'integer',
        'unit_cost_idr' => 'integer',
        'total_cogs_idr' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('IngredientConsumption records are append-only.');
        });

        static::deleting(function (): void {
            throw new DomainException('IngredientConsumption records are append-only.');
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CateringOrder::class, 'order_id');
    }
}
