<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EnterpriseBudget extends Model
{
    protected $table = 'ef_budgets';

    protected $guarded = [];

    protected $casts = [
        'allocated_amount_idr' => 'integer',
        'encumbered_amount_idr' => 'integer',
        'spent_amount_idr' => 'integer',
    ];
}
