<?php

declare(strict_types=1);

namespace Modules\Store\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Modules\Inventory\Domain\Models\StockMovement;

class Product extends Model
{
    use HasFactory;

    protected $table = 'store_products';

    protected $fillable = [
        'uuid',
        'productable_type',
        'productable_id',
        'seller_id',
        'category_id',
        'sku',
        'name',
        'slug',
        'description',
        'price',
        'compare_at_price',
        'cached_stock',
        'is_listed',
        'is_car',
        'weight_gram',
        'images',
    ];

    protected $casts = [
        'price' => 'integer',
        'compare_at_price' => 'integer',
        'cached_stock' => 'integer',
        'is_listed' => 'boolean',
        'is_car' => 'boolean',
        'weight_gram' => 'integer',
        'images' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->uuid)) {
                $product->uuid = (string) Str::uuid();
            }
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name.'-'.Str::random(5));
            }
        });
    }

    public function productable(): MorphTo
    {
        return $this->morphTo();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'product_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    public function scopeListed(Builder $query): Builder
    {
        return $query->where('is_listed', true);
    }

    public function scopeCars(Builder $query): Builder
    {
        return $query->where('is_car', true);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Produk C2C: mobil bekas milik pengguna lain, dibayar lewat escrow.
     */
    public function isC2c(): bool
    {
        return $this->productable_type === 'core_vehicle' && $this->seller_id !== null;
    }

    public function scopeC2c(Builder $query): Builder
    {
        return $query->where('productable_type', 'core_vehicle')->whereNotNull('seller_id');
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'Rp '.number_format($this->price, 0, ',', '.');
    }

    public function getPrimaryImageAttribute(): string
    {
        if (! empty($this->images) && is_array($this->images) && isset($this->images[0])) {
            return $this->images[0];
        }

        if ($this->is_car) {
            return 'https://images.unsplash.com/photo-1617788138017-80ad40651399?auto=format&fit=crop&w=800&q=80';
        }

        return 'https://images.unsplash.com/photo-1486006920555-c77dce18193b?auto=format&fit=crop&w=800&q=80';
    }
}
