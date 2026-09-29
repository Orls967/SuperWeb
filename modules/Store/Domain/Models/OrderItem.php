<?php

declare(strict_types=1);

namespace Modules\Store\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'store_order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'name_snapshot',
        'price_snapshot',
        'qty',
        'line_total',
        'reservation_id',
    ];

    protected $casts = [
        'price_snapshot' => 'integer',
        'qty' => 'integer',
        'line_total' => 'integer',
        'reservation_id' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
