<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecondaryLedgerEntry extends Model
{
    protected $table = 'ven_secondary_ledger_entries';

    protected $fillable = [
        'entry_code',
        'resale_id',
        'debit_account',
        'credit_account',
        'amount_idr',
        'description',
    ];

    protected $casts = [
        'amount_idr' => 'integer',
    ];

    public function resale(): BelongsTo
    {
        return $this->belongsTo(TicketResale::class, 'resale_id');
    }
}
