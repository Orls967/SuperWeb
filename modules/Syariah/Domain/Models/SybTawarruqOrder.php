<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SybTawarruqOrder extends Model
{
    protected $table = 'syb_tawarruq_orders';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'commodity_lot_id',
        'order_number',
        'customer_party_ref',
        'source_currency',
        'source_amount_cents',
        'target_currency',
        'target_amount_cents',
        'spot_rate_bps',
        'tenor_days',
        'markup_rate_bps',
        'markup_profit_cents',
        'broker_fee_cents',
        'total_deferred_price_cents',
        'effective_rate_bps',
        'certificate_hash',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'source_amount_cents' => 'integer',
        'target_amount_cents' => 'integer',
        'spot_rate_bps' => 'integer',
        'tenor_days' => 'integer',
        'markup_rate_bps' => 'integer',
        'markup_profit_cents' => 'integer',
        'broker_fee_cents' => 'integer',
        'total_deferred_price_cents' => 'integer',
        'effective_rate_bps' => 'integer',
    ];

    /**
     * @return BelongsTo<SybCommodityLot, $this>
     */
    public function commodityLot(): BelongsTo
    {
        return $this->belongsTo(SybCommodityLot::class, 'commodity_lot_id');
    }

    /**
     * @return HasMany<SybTawarruqTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(SybTawarruqTransaction::class, 'order_id');
    }
}
