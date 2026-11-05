<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybRestructuredInstallment extends Model
{
    protected $table = 'syb_restructured_installments';

    protected $fillable = [
        'request_ref',
        'month_number',
        'is_grace',
        'principal_minor',
        'margin_minor',
        'total_installment_minor',
        'remaining_principal_minor',
        'remaining_margin_minor',
        'due_date',
        'payment_status',
        'paid_at',
        'ledger_journal_ref',
    ];

    protected function casts(): array
    {
        return [
            'month_number' => 'integer',
            'is_grace' => 'boolean',
            'principal_minor' => 'integer',
            'margin_minor' => 'integer',
            'total_installment_minor' => 'integer',
            'remaining_principal_minor' => 'integer',
            'remaining_margin_minor' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SybRestructuringRequest::class, 'request_ref', 'request_ref');
    }
}
