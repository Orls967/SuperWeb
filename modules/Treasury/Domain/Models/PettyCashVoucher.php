<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashVoucher extends Model
{
    use HasUuids;

    protected $table = 'trs_petty_cash_vouchers';

    protected $fillable = [
        'bank_account_id',
        'voucher_number',
        'voucher_date',
        'amount',
        'payee',
        'expense_account_code',
        'description',
        'status',
        'created_by',
        'approved_by',
        'ledger_tx_id',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'amount' => 'integer',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'ledger_tx_id' => 'integer',
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
