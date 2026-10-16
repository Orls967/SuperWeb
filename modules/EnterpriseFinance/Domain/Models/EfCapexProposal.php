<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EfCapexProposal extends Model
{
    protected $table = 'ef_capex_proposals';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'proposal_code',
        'line_code',
        'project_title',
        'requested_budget_minor',
        'allocated_treasury_budget_minor',
        'actual_spent_minor',
        'irr_pct',
        'npv_minor',
        'esg_score',
        'status',
    ];

    protected $casts = [
        'requested_budget_minor' => 'integer',
        'allocated_treasury_budget_minor' => 'integer',
        'actual_spent_minor' => 'integer',
        'irr_pct' => 'float',
        'npv_minor' => 'integer',
        'esg_score' => 'float',
    ];
}
