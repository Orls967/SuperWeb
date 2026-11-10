<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationUnmatched extends Model
{
    use HasUuids;

    protected $table = 'trs_bank_reconciliation_unmatched';

    protected $fillable = [
        'statement_id',
        'reason',
        'notes',
        'status',
        'reported_by',
    ];

    protected $casts = [
        'reported_by' => 'integer',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'statement_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
