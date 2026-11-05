<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class SybRecoveryReceipt extends Model
{
    protected $table = 'syb_recovery_receipts';

    protected $fillable = [
        'receipt_ref',
        'financing_ref',
        'recovery_source',
        'amount_minor',
        'principal_recovery_minor',
        'margin_recovery_minor',
        'allocated_to_charity_minor',
        'ledger_journal_ref',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'principal_recovery_minor' => 'integer',
            'margin_recovery_minor' => 'integer',
            'allocated_to_charity_minor' => 'integer',
        ];
    }
}
