<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class IntercompanyLoan extends Model
{
    protected $table = 'ic_loans';

    protected $fillable = [
        'loan_agreement_number',
        'loan_number',
        'lender_entity',
        'borrower_entity',
        'lender_entity_id',
        'borrower_entity_id',
        'principal_idr',
        'principal_minor',
        'currency',
        'base_rate_bps',
        'spread_bps',
        'interest_rate_bps',
        'withholding_tax_bps',
        'arms_length_interest_rate',
        'periods',
        'repaid_principal_idr',
        'repaid_principal_minor',
        'start_date',
        'due_date',
        'status',
        'ledger_transaction_id',
        'created_by',
        'approved_by',
    ];

    public function getLoanNumberAttribute(): ?string
    {
        return $this->attributes['loan_agreement_number'] ?? null;
    }

    public function setLoanNumberAttribute(?string $val): void
    {
        $this->attributes['loan_agreement_number'] = $val;
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->lender_entity) && ! empty($model->lender_entity_id)) {
                $model->lender_entity = IntercompanyEntity::find($model->lender_entity_id)?->entity_code ?? 'ENTITY_'.$model->lender_entity_id;
            }
            if (empty($model->borrower_entity) && ! empty($model->borrower_entity_id)) {
                $model->borrower_entity = IntercompanyEntity::find($model->borrower_entity_id)?->entity_code ?? 'ENTITY_'.$model->borrower_entity_id;
            }
            if (empty($model->principal_idr) && ! empty($model->principal_minor)) {
                $model->principal_idr = $model->principal_minor;
            }
        });
    }

    protected $casts = [
        'principal_idr' => 'integer',
        'principal_minor' => 'integer',
        'interest_rate_bps' => 'integer',
        'spread_bps' => 'integer',
        'withholding_tax_bps' => 'integer',
        'repaid_principal_idr' => 'integer',
        'repaid_principal_minor' => 'integer',
    ];

    public function lender(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'lender_entity_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'borrower_entity_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(IntercompanyLoanSchedule::class, 'loan_id')->orderBy('installment_number');
    }

    public function recordRepayment(int $amountIdr): void
    {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nominal pelunasan pokok harus lebih besar dari nol.');
        }

        if (($this->repaid_principal_idr + $amountIdr) > $this->principal_idr) {
            throw new InvalidArgumentException('Pelunasan pokok tidak boleh melebihi sisa pokok pinjaman.');
        }

        $this->repaid_principal_idr += $amountIdr;
        $this->repaid_principal_minor += $amountIdr;
        if ($this->repaid_principal_idr === $this->principal_idr) {
            $this->status = 'settled';
        }
        $this->save();
    }
}
