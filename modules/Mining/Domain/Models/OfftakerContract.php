<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class OfftakerContract extends Model
{
    protected $table = 'min_offtaker_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contract_number',
        'buyer_party_id',
        'commodity',
        'contracted_tonnage',
        'base_lme_price_usd_per_ton',
        'premium_discount_usd',
        'invoiced_amount_minor',
        'currency',
        'settlement_status',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'contracted_tonnage' => 'float',
        'base_lme_price_usd_per_ton' => 'decimal:2',
        'premium_discount_usd' => 'float',
        'invoiced_amount_minor' => 'integer',
    ];
}
