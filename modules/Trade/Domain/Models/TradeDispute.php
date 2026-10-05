<?php

declare(strict_types=1);

namespace Modules\Trade\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TradeDispute extends Model
{
    use HasUuids;

    protected $table = 'trd_trade_disputes';

    protected $fillable = [
        'dispute_number',
        'order_reference_no',
        'claim_reason',
        'claim_amount_idr',
        'insurance_payout_idr',
        'status',
    ];

    protected $casts = [
        'claim_amount_idr' => 'integer',
        'insurance_payout_idr' => 'integer',
    ];
}
