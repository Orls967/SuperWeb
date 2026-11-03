<?php

declare(strict_types=1);

namespace Modules\Circular\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CirListing extends Model
{
    protected $table = 'cir_listings';

    protected $fillable = [
        'listing_code',
        'seller_party_id',
        'auction_round_id',
        'material_code',
        'grade_code',
        'available_grams',
        'unit_price_per_kg_idr',
        'mode',
        'status',
        'origin_location_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'seller_party_id' => 'integer',
            'auction_round_id' => 'integer',
            'available_grams' => 'integer',
            'unit_price_per_kg_idr' => 'integer',
            'origin_location_id' => 'integer',
        ];
    }

    public function auctionRound(): BelongsTo
    {
        return $this->belongsTo(CirAuctionRound::class, 'auction_round_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CirOrder::class, 'listing_id');
    }
}
