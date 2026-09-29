<?php

declare(strict_types=1);

namespace Modules\Store\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $table = 'store_cart_items';

    protected $fillable = [
        'cart_id',
        'product_id',
        'qty',
        'price_snapshot',
    ];

    protected $casts = [
        'qty' => 'integer',
        'price_snapshot' => 'integer',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getLineTotalAttribute(): int
    {
        $unitPrice = $this->price_snapshot ?: ($this->product?->price ?? 0);

        return (int) ($this->qty * $unitPrice);
    }
}
