<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EsgMarketplaceListing extends Model
{
    use HasFactory;

    protected $table = 'egy_esg_marketplace_listings';

    protected $fillable = [
        'listing_code',
        'title',
        'category',
        'description',
        'seller_party_id',
        'price_minor_per_unit',
        'unit_label',
        'available_quantity',
        'sold_quantity',
        'status',
    ];

    protected $casts = [
        'seller_party_id' => 'integer',
        'price_minor_per_unit' => 'integer',
        'available_quantity' => 'integer',
        'sold_quantity' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(EsgMarketplaceOrder::class, 'listing_id');
    }
}
