<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RwaOrderbookOrder extends Model
{
    protected $table = 'rwa_orderbook_orders';

    protected $fillable = [
        'order_code',
        'rwa_asset_id',
        'user_id',
        'side',
        'quantity',
        'filled_quantity',
        'price_per_token_idr',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'filled_quantity' => 'integer',
        'price_per_token_idr' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(RwaAsset::class, 'rwa_asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
