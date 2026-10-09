<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasUuids;

    protected $table = 'trs_bank_accounts';

    protected $fillable = [
        'account_number',
        'bank_name',
        'currency',
        'balance',
        'is_petty_cash',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'integer',
        'is_petty_cash' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function statements(): HasMany
    {
        return $this->hasMany(BankStatement::class, 'bank_account_id');
    }
}
