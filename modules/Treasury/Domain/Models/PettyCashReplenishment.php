<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashReplenishment extends Model
{
    use HasUuids;

    protected $table = 'trs_petty_cash_replenishments';

    protected $fillable = [
        'bank_account_id',
        'replenishment_number',
        'replenishment_date',
        'imprest_limit_idr',
        'book_balance_before_idr',
        'physical_cash_counted_idr',
        'shortage_overage_idr',
        'replenished_amount_idr',
        'balance_after_idr',
        'source_bank_account_code',
        'ledger_tx_id',
        'status',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'replenishment_date' => 'date',
        'imprest_limit_idr' => 'integer',
        'book_balance_before_idr' => 'integer',
        'physical_cash_counted_idr' => 'integer',
        'shortage_overage_idr' => 'integer',
        'replenished_amount_idr' => 'integer',
        'balance_after_idr' => 'integer',
        'ledger_tx_id' => 'integer',
        'created_by' => 'integer',
        'approved_by' => 'integer',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
