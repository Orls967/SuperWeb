<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Finance\Domain\Enums\InstallmentStatus;
use Modules\Shared\Domain\Exceptions\InvalidStateTransition;

class Installment extends Model
{
    use HasFactory;

    protected $table = 'fin_installments';

    protected $fillable = [
        'loan_id',
        'sequence',
        'due_date',
        'principal_part',
        'interest_part',
        'amount',
        'penalty',
        'status',
        'paid_at',
        'ledger_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_date' => 'date',
            'principal_part' => 'integer',
            'interest_part' => 'integer',
            'amount' => 'integer',
            'penalty' => 'integer',
            'status' => InstallmentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    public function transitionTo(InstallmentStatus $next): self
    {
        if (! $this->status->canTransitionTo($next)) {
            throw InvalidStateTransition::fromTo($this->status, $next, 'Installment');
        }

        $this->status = $next;
        $this->save();

        return $this;
    }

    /**
     * Jumlah hari keterlambatan dihitung dari tanggal jatuh tempo.
     */
    public function daysLate(): int
    {
        if ($this->status === InstallmentStatus::Paid) {
            return 0;
        }

        $due = $this->due_date->copy()->startOfDay();

        if ($due->isFuture()) {
            return 0;
        }

        // Carbon 3 mengembalikan float pada diffInDays
        return (int) floor($due->diffInDays(now()->startOfDay()));
    }

    /**
     * Denda 0,1% per hari dari nilai cicilan.
     */
    public function calculatePenalty(): int
    {
        $days = $this->daysLate();

        if ($days <= 0) {
            return 0;
        }

        return (int) floor($this->amount * Loan::DAILY_PENALTY_RATE * $days);
    }

    public function totalDue(): int
    {
        return $this->amount + $this->calculatePenalty();
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp '.number_format($this->amount, 0, ',', '.');
    }
}
