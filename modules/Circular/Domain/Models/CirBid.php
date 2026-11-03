<?php

declare(strict_types=1);

namespace Modules\Circular\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CirBid extends Model
{
    protected $table = 'cir_bids';

    protected $fillable = [
        'auction_round_id',
        'buyer_party_id',
        'quantity_grams',
        'max_price_per_kg_idr',
        'allocated_grams',
        'status',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'auction_round_id' => 'integer',
            'buyer_party_id' => 'integer',
            'quantity_grams' => 'integer',
            'max_price_per_kg_idr' => 'integer',
            'allocated_grams' => 'integer',
        ];
    }

    public function auctionRound(): BelongsTo
    {
        return $this->belongsTo(CirAuctionRound::class, 'auction_round_id');
    }
}
