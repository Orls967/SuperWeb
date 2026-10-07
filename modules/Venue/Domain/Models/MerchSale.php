<?php

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchSale extends Model
{
    protected $table = 'ven_merch_sales';

    protected $fillable = [
        'sale_code',
        'creator_id',
        'item_name',
        'total_sale_price_idr',
        'artist_share_idr',
        'platform_share_idr',
        'status',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'creator_id');
    }
}
