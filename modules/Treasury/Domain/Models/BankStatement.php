<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankStatement extends Model
{
    use HasUuids;

    protected $table = 'trs_bank_statements';

    protected $fillable = [
        'bank_account_id',
        'transaction_date',
        'reference_no',
        'bank_code',
        'amount',
        'description',
        'raw_payload',
        'is_reconciled',
        'reconciliation_status',
        'matched_ledger_tx_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'integer',
        'raw_payload' => 'array',
        'is_reconciled' => 'boolean',
        'matched_ledger_tx_id' => 'integer',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function matchRecord(): HasOne
    {
        return $this->hasOne(BankReconciliationMatch::class, 'statement_id');
    }

    public function unmatchedRecords(): HasMany
    {
        return $this->hasMany(BankReconciliationUnmatched::class, 'statement_id');
    }
}
