<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Resto\Domain\Enums\ServiceStyle;
use Modules\Shared\Domain\Traits\HasUuid;

class MenuItem extends Model
{
    use HasUuid;

    protected $table = 'resto_menu_items';

    protected $fillable = [
        'uuid',
        'category_id',
        'sku',
        'name',
        'slug',
        'description',
        'service_style',
        'base_price',
        'takeaway_price',
        'is_active',
        'is_halal_certified',
        'spice_level',
        'images',
        'sort',
    ];

    protected $casts = [
        'service_style' => ServiceStyle::class,
        'base_price' => 'integer',
        'takeaway_price' => 'integer',
        'is_active' => 'boolean',
        'is_halal_certified' => 'boolean',
        'spice_level' => 'integer',
        'images' => 'array',
        'sort' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class, 'menu_item_id')->where('is_active', true);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'menu_item_id');
    }

    public function outletOverrides(): HasMany
    {
        return $this->hasMany(MenuItemOutlet::class, 'menu_item_id');
    }

    public function priceForOutlet(?int $outletId = null, bool $isTakeaway = false): int
    {
        if ($outletId !== null) {
            $override = $this->outletOverrides()->where('outlet_id', $outletId)->first();
            if ($override && $override->price_override !== null) {
                return (int) $override->price_override;
            }
        }

        return $isTakeaway && $this->takeaway_price > 0 ? $this->takeaway_price : $this->base_price;
    }

    public function isAvailableForOutlet(?int $outletId = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($outletId !== null) {
            $override = $this->outletOverrides()->where('outlet_id', $outletId)->first();
            if ($override && ! $override->is_available) {
                return false;
            }
        }

        return true;
    }
}
