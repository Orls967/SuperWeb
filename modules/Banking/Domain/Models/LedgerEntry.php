<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Domain\ValueObjects\Money;

class LedgerEntry extends Model
{
    public $timestamps = false;

    protected $table = 'bank_ledger_entries';

    protected $fillable = [
        'transaction_id',
        'account_id',
        'asset_code',
        'amount',
        'balance_after',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'string',
        'balance_after' => 'string',
        'created_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    public function money(): Money
    {
        return Money::of($this->asset_code, $this->amount);
    }

    public function balanceAfterMoney(): Money
    {
        return Money::of($this->asset_code, $this->balance_after);
    }
}
