<?php

namespace Modules\B2b\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SurplusAuction extends Model
{
    use HasUuids;

    protected $table = 'b2b_auctions';

    protected $fillable = [
        'lot_number',
        'seller_id',
        'asset_type',
        'asset_reference_id',
        'title',
        'auction_type',
        'starting_bid_idr',
        'reserve_price_idr',
        'bid_increment_idr',
        'current_highest_bid_idr',
        'winning_bidder_id',
        'starts_at',
        'ends_at',
        'anti_sniping_enabled',
        'status',
    ];

    protected $casts = [
        'starting_bid_idr' => 'integer',
        'reserve_price_idr' => 'integer',
        'bid_increment_idr' => 'integer',
        'current_highest_bid_idr' => 'integer',
        'anti_sniping_enabled' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}
