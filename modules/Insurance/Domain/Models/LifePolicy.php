<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LifePolicy extends Model
{
    protected $table = 'ins_lh_life_policies';

    /** @var list<string> */
    protected $fillable = [
        'policy_number',
        'insured_party_id',
        'plan_type',
        'entry_age',
        'term_years',
        'sum_insured_idr',
        'annual_premium_idr',
        'saving_accumulated_idr',
        'status',
        'issued_date',
        'maturity_date',
        'ledger_transaction_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'entry_age' => 'integer',
            'term_years' => 'integer',
            'sum_insured_idr' => 'integer',
            'annual_premium_idr' => 'integer',
            'saving_accumulated_idr' => 'integer',
            'issued_date' => 'date',
            'maturity_date' => 'date',
            'ledger_transaction_id' => 'integer',
        ];
    }

    /** @return HasMany<LifeBeneficiary, $this> */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(LifeBeneficiary::class, 'life_policy_id');
    }

    /** @return HasMany<UnitLinkHolding, $this> */
    public function unitHoldings(): HasMany
    {
        return $this->hasMany(UnitLinkHolding::class, 'life_policy_id');
    }
}
