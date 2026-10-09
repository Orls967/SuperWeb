<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyBankAccount extends Model
{
    protected $table = 'pty_bank_accounts';

    protected $fillable = [
        'party_id', 'bank_name', 'bank_code',
        'account_number_masked', 'account_number_hash',
        'account_holder_name', 'currency', 'is_primary', 'is_verified',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }
}
