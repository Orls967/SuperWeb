<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasUuids;

    protected $table = 'trs_bank_accounts';

    protected $fillable = [
        'account_number',
        'entity_id',
        'bank_name',
        'currency',
        'balance',
        'imprest_limit',
        'is_petty_cash',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'integer',
        'imprest_limit' => 'integer',
        'is_petty_cash' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(BankStatement::class, 'bank_account_id');
    }

    public function pettyCashVouchers(): HasMany
    {
        return $this->hasMany(PettyCashVoucher::class, 'bank_account_id');
    }
}
