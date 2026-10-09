<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCenter extends Model
{
    protected $table = 'prc_budget_centers';

    protected $fillable = ['code', 'name', 'annual_budget_idr', 'is_active'];

    protected $casts = ['annual_budget_idr' => 'integer', 'is_active' => 'boolean'];

    public function encumbrances(): HasMany
    {
        return $this->hasMany(BudgetEncumbrance::class, 'budget_center_id');
    }

    public function activeEncumbranceTotal(): int
    {
        return (int) $this->encumbrances()->where('status', 'active')->sum('amount_idr');
    }

    public function remainingBudget(): int
    {
        return (int) $this->annual_budget_idr - $this->activeEncumbranceTotal();
    }
}
