<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatement extends Model
{
    use HasUuids;

    protected $table = 'trs_bank_statements';

    protected $fillable = [
        'bank_account_id',
        'transaction_date',
        'reference_no',
        'amount',
        'description',
        'is_reconciled',
        'matched_ledger_tx_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'integer',
        'is_reconciled' => 'boolean',
        'matched_ledger_tx_id' => 'integer',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
