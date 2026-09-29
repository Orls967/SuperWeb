<?php

declare(strict_types=1);

namespace Modules\Store\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $table = 'store_carts';

    protected $fillable = [
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }

    public function getSubtotalAttribute(): int
    {
        return (int) $this->items->sum(function (CartItem $item) {
            return $item->line_total;
        });
    }

    public function getTotalWeightGramAttribute(): int
    {
        return (int) $this->items->sum(function (CartItem $item) {
            return ($item->product?->weight_gram ?? 500) * $item->qty;
        });
    }

    public function getItemsCountAttribute(): int
    {
        return (int) $this->items->sum('qty');
    }
}
