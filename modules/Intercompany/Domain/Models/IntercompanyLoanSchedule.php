<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntercompanyLoanSchedule extends Model
{
    protected $table = 'ic_loan_schedules';

    protected $fillable = [
        'loan_id',
        'installment_number',
        'due_date',
        'beginning_balance_minor',
        'principal_due_minor',
        'interest_due_minor',
        'tax_withheld_minor',
        'net_interest_minor',
        'ending_balance_minor',
        'payment_status',
        'paid_at',
        'ledger_transaction_id',
    ];

    public function getStatusAttribute(): ?string
    {
        return $this->attributes['payment_status'] ?? null;
    }

    public function setStatusAttribute(?string $val): void
    {
        $this->attributes['payment_status'] = $val;
    }

    protected $casts = [
        'installment_number' => 'integer',
        'beginning_balance_minor' => 'integer',
        'principal_due_minor' => 'integer',
        'interest_due_minor' => 'integer',
        'tax_withheld_minor' => 'integer',
        'net_interest_minor' => 'integer',
        'ending_balance_minor' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(IntercompanyLoan::class, 'loan_id');
    }
}
