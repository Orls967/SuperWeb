<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class SybCollateralAuction extends Model
{
    protected $table = 'syb_collateral_auctions';

    protected $fillable = [
        'auction_ref',
        'financing_ref',
        'collateral_description',
        'appraised_value_minor',
        'reserve_price_minor',
        'winning_bid_minor',
        'buyer_ref',
        'auction_date',
        'surplus_refunded_to_customer_minor',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'appraised_value_minor' => 'integer',
            'reserve_price_minor' => 'integer',
            'winning_bid_minor' => 'integer',
            'surplus_refunded_to_customer_minor' => 'integer',
            'auction_date' => 'date',
        ];
    }
}
