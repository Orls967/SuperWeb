<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ContractorSafetyEvaluation extends Model
{
    protected $table = 'min_contractor_safety_evaluations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'contractor_party_id',
        'safety_score',
        'total_incidents',
        'tier',
        'tender_eligible',
        'penalty_amount_minor',
        'penalty_ledger_tx_id',
    ];

    protected $casts = [
        'safety_score' => 'integer',
        'total_incidents' => 'integer',
        'tender_eligible' => 'boolean',
        'penalty_amount_minor' => 'integer',
    ];
}
