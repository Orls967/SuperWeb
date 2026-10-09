<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashPool extends Model
{
    use HasUuids;

    protected $table = 'trs_cash_pools';

    protected $fillable = [
        'pool_name',
        'header_account_id',
        'sub_account_id',
        'target_balance_idr',
        'last_swept_amount_idr',
        'last_swept_at',
    ];

    protected $casts = [
        'target_balance_idr' => 'integer',
        'last_swept_amount_idr' => 'integer',
        'last_swept_at' => 'datetime',
    ];

    public function headerAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'header_account_id');
    }

    public function subAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'sub_account_id');
    }
}
