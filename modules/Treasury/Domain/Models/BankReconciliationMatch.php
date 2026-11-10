<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationMatch extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'trs_bank_reconciliation_matches';

    protected $fillable = [
        'statement_id',
        'match_type',
        'matched_amount',
        'ledger_entry_ids',
        'ledger_tx_id',
        'matched_by',
        'notes',
        'match_certificate',
        'created_at',
    ];

    protected $casts = [
        'matched_amount' => 'integer',
        'ledger_entry_ids' => 'array',
        'ledger_tx_id' => 'integer',
        'matched_by' => 'integer',
        'match_certificate' => 'array',
        'created_at' => 'datetime',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'statement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }
}
