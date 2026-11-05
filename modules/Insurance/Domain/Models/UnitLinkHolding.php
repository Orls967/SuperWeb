<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitLinkHolding extends Model
{
    protected $table = 'ins_lh_unit_link_holdings';

    /** @var list<string> */
    protected $fillable = [
        'life_policy_id',
        'fund_id',
        'units_held',
        'acquisition_cost_idr',
        'current_valuation_idr',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'life_policy_id' => 'integer',
            'fund_id' => 'integer',
            'units_held' => 'integer',
            'acquisition_cost_idr' => 'integer',
            'current_valuation_idr' => 'integer',
        ];
    }

    /** @return BelongsTo<LifePolicy, $this> */
    public function lifePolicy(): BelongsTo
    {
        return $this->belongsTo(LifePolicy::class, 'life_policy_id');
    }

    /** @return BelongsTo<UnitLinkFund, $this> */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(UnitLinkFund::class, 'fund_id');
    }
}
