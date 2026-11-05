<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SybBmtSavings extends Model
{
    protected $table = 'syb_bmt_savings';

    protected $fillable = [
        'account_number',
        'cooperative_code',
        'customer_party_ref',
        'contract_type',
        'balance_idr',
        'is_active',
    ];

    protected $casts = [
        'balance_idr' => 'integer',
        'is_active' => 'boolean',
    ];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(SybBmtCooperative::class, 'cooperative_code', 'cooperative_code');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SybBmtSavingsTransaction::class, 'account_number', 'account_number');
    }
}
