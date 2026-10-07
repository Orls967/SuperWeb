<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EfCapexProposal extends Model
{
    protected $table = 'ef_capex_proposals';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'requested_budget_minor' => 'integer',
        'allocated_treasury_budget_minor' => 'integer',
        'actual_spent_minor' => 'integer',
        'irr_pct' => 'float',
        'npv_minor' => 'integer',
        'esg_score' => 'float',
    ];
}
