<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnterpriseBudget extends Model
{
    protected $table = 'ef_budgets';

    protected $fillable = [
        'budget_code',
        'fiscal_year',
        'cost_center_code',
        'account_code',
        'allocated_amount_idr',
        'encumbered_amount_idr',
        'spent_amount_idr',
        'allocated_amount_minor',
        'encumbered_amount_minor',
        'spent_amount_minor',
        'control_type',
        'status',
        'created_by',
        'approved_by',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'allocated_amount_idr' => 'integer',
        'encumbered_amount_idr' => 'integer',
        'spent_amount_idr' => 'integer',
    ];

    public function encumbrances(): HasMany
    {
        return $this->hasMany(BudgetEncumbrance::class, 'budget_id');
    }

    public function getAllocatedAmountMinorAttribute(): int
    {
        return (int) ($this->attributes['allocated_amount_idr'] ?? 0);
    }

    public function setAllocatedAmountMinorAttribute(int $value): void
    {
        $this->attributes['allocated_amount_idr'] = $value;
    }

    public function getEncumberedAmountMinorAttribute(): int
    {
        return (int) ($this->attributes['encumbered_amount_idr'] ?? 0);
    }

    public function setEncumberedAmountMinorAttribute(int $value): void
    {
        $this->attributes['encumbered_amount_idr'] = $value;
    }

    public function getSpentAmountMinorAttribute(): int
    {
        return (int) ($this->attributes['spent_amount_idr'] ?? 0);
    }

    public function setSpentAmountMinorAttribute(int $value): void
    {
        $this->attributes['spent_amount_idr'] = $value;
    }

    public function getEntityCodeAttribute(): string
    {
        return (string) ($this->attributes['cost_center_code'] ?? 'CORP-HOLDING');
    }

    public function setEntityCodeAttribute(string $value): void
    {
        if (empty($this->attributes['cost_center_code'])) {
            $this->attributes['cost_center_code'] = $value;
        }
    }

    public function getDepartmentCodeAttribute(): string
    {
        return (string) ($this->attributes['account_code'] ?? 'DEFAULT');
    }

    public function setDepartmentCodeAttribute(string $value): void
    {
        if (empty($this->attributes['account_code'])) {
            $this->attributes['account_code'] = $value;
        }
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->budget_code)) {
                $fy = $model->fiscal_year ?? date('Y');
                $cc = $model->cost_center_code ?? 'GEN';
                $ac = $model->account_code ?? 'OPS';
                $model->budget_code = "BDG-{$fy}-{$cc}-{$ac}-".substr(bin2hex(random_bytes(3)), 0, 6);
            }
            if (empty($model->cost_center_code)) {
                $model->cost_center_code = 'CORP-HOLDING';
            }
            if (empty($model->account_code)) {
                $model->account_code = 'GEN-OPS';
            }
            if (! isset($model->allocated_amount_idr)) {
                $model->allocated_amount_idr = 0;
            }
        });
    }
}
