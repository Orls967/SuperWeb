<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SybSalamContract extends Model
{
    protected $table = 'syb_salam_trade_contracts';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'contract_number',
        'seller_party_ref',
        'buyer_party_ref',
        'commodity_code',
        'commodity_grade',
        'quantity_unit',
        'quantity_ordered',
        'quantity_delivered',
        'advance_payment_cents',
        'promised_delivery_date',
        'delivery_warehouse_code',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_delivered' => 'integer',
        'advance_payment_cents' => 'integer',
        'promised_delivery_date' => 'date',
    ];

    /**
     * @return HasMany<SybParallelSalamHedge, $this>
     */
    public function parallelHedges(): HasMany
    {
        return $this->hasMany(SybParallelSalamHedge::class, 'salam_id');
    }

    /**
     * @return HasMany<SybSalamDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(SybSalamDelivery::class, 'salam_id');
    }
}
