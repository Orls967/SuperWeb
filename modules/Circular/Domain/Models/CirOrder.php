<?php

declare(strict_types=1);

namespace Modules\Circular\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CirOrder extends Model
{
    protected $table = 'cir_orders';

    protected $fillable = [
        'order_number',
        'listing_id',
        'auction_round_id',
        'buyer_party_id',
        'seller_party_id',
        'material_code',
        'grade_code',
        'ordered_grams',
        'accepted_grams',
        'unit_price_per_kg_idr',
        'escrow_amount_idr',
        'refund_amount_idr',
        'status',
        'origin_location_id',
        'destination_facility_id',
        'reverse_shipment_id',
        'plant_receipt_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'listing_id' => 'integer',
            'auction_round_id' => 'integer',
            'buyer_party_id' => 'integer',
            'seller_party_id' => 'integer',
            'ordered_grams' => 'integer',
            'accepted_grams' => 'integer',
            'unit_price_per_kg_idr' => 'integer',
            'escrow_amount_idr' => 'integer',
            'refund_amount_idr' => 'integer',
            'origin_location_id' => 'integer',
            'destination_facility_id' => 'integer',
            'reverse_shipment_id' => 'integer',
            'plant_receipt_id' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(CirListing::class, 'listing_id');
    }

    public function auctionRound(): BelongsTo
    {
        return $this->belongsTo(CirAuctionRound::class, 'auction_round_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CirOrderLine::class, 'order_id');
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(CirSettlement::class, 'order_id');
    }
}
