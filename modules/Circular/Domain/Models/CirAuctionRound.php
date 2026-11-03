<?php

declare(strict_types=1);

namespace Modules\Circular\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CirAuctionRound extends Model
{
    protected $table = 'cir_auction_rounds';

    protected $fillable = [
        'round_code',
        'material_code',
        'grade_code',
        'origin_location_id',
        'destination_facility_id',
        'status',
        'clearing_price_per_kg_idr',
        'cleared_volume_grams',
        'idempotency_key',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'origin_location_id' => 'integer',
            'destination_facility_id' => 'integer',
            'clearing_price_per_kg_idr' => 'integer',
            'cleared_volume_grams' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(CirListing::class, 'auction_round_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(CirBid::class, 'auction_round_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CirOrder::class, 'auction_round_id');
    }
}
