<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetCashbackTransaction extends Model
{
    protected $table = 'ret_cashback_transactions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'transaction_amount_minor' => 'integer',
        'cashback_pct' => 'float',
        'cashback_earned_minor' => 'integer',
    ];
}
