<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class SybTradeSettlement extends Model
{
    protected $table = 'syb_trade_settlements';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'settlement_number',
        'trade_contract_type',
        'contract_reference',
        'payer_party_ref',
        'payee_party_ref',
        'amount_cents',
        'currency',
        'ujrah_fee_cents',
        'settlement_channel',
        'settled_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount_cents' => 'integer',
        'ujrah_fee_cents' => 'integer',
        'settled_at' => 'datetime',
    ];
}
