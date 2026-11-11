<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyndicatedFacility extends Model
{
    protected $table = 'ef_syndicated_facilities';

    protected $fillable = [
        'facility_code',
        'facility_name',
        'facility_type',
        'lead_arranger',
        'currency',
        'total_commitment_minor',
        'drawn_amount_minor',
        'repaid_amount_minor',
        'interest_rate_bps',
        'spread_bps',
        'start_date',
        'maturity_date',
        'status',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'total_commitment_minor' => 'integer',
        'drawn_amount_minor' => 'integer',
        'repaid_amount_minor' => 'integer',
        'interest_rate_bps' => 'integer',
        'spread_bps' => 'integer',
        'start_date' => 'date',
        'maturity_date' => 'date',
        'ledger_transaction_id' => 'integer',
    ];

    public function covenantEvaluations(): HasMany
    {
        return $this->hasMany(DebtCovenantEvaluation::class, 'facility_id');
    }

    public function availableCommitmentMinor(): int
    {
        return max(0, $this->total_commitment_minor - $this->drawn_amount_minor);
    }

    public function outstandingPrincipalMinor(): int
    {
        return max(0, $this->drawn_amount_minor - $this->repaid_amount_minor);
    }
}
