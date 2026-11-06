<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CarbonTrade extends Model
{
    use HasFactory;

    protected $table = 'egy_carbon_trades';

    protected $fillable = [
        'trade_number',
        'buy_order_id',
        'sell_order_id',
        'buyer_party_id',
        'seller_party_id',
        'matched_kg_co2e',
        'execution_price_minor_per_ton',
        'total_amount_idr',
        'platform_fee_idr',
        'ledger_transaction_id',
        'settlement_status',
        'certificate_hash',
    ];

    protected $casts = [
        'buy_order_id' => 'integer',
        'sell_order_id' => 'integer',
        'buyer_party_id' => 'integer',
        'seller_party_id' => 'integer',
        'matched_kg_co2e' => 'integer',
        'execution_price_minor_per_ton' => 'integer',
        'total_amount_idr' => 'integer',
        'platform_fee_idr' => 'integer',
        'ledger_transaction_id' => 'integer',
    ];

    public function buyOrder(): BelongsTo
    {
        return $this->belongsTo(CarbonOrder::class, 'buy_order_id');
    }

    public function sellOrder(): BelongsTo
    {
        return $this->belongsTo(CarbonOrder::class, 'sell_order_id');
    }
}
