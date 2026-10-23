<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

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
        'control_type',
    ];

    protected $casts = [
        'allocated_amount_idr' => 'integer',
        'encumbered_amount_idr' => 'integer',
        'spent_amount_idr' => 'integer',
    ];
}
