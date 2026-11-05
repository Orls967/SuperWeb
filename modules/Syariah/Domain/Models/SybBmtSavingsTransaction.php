<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SybBmtSavingsTransaction extends Model
{
    protected $table = 'syb_bmt_savings_transactions';

    protected $fillable = [
        'transaction_ref',
        'account_number',
        'type',
        'amount_idr',
        'balance_after_idr',
        'note',
    ];

    protected $casts = [
        'amount_idr' => 'integer',
        'balance_after_idr' => 'integer',
    ];

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SybBmtSavings::class, 'account_number', 'account_number');
    }
}

