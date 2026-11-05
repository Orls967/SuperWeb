<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitLinkFund extends Model
{
    protected $table = 'ins_lh_unit_link_funds';

    /** @var list<string> */
    protected $fillable = [
        'fund_code',
        'name',
        'asset_class',
        'current_nav_per_unit_idr',
        'total_aum_idr',
        'total_units',
        'management_fee_bps',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'current_nav_per_unit_idr' => 'integer',
            'total_aum_idr' => 'integer',
            'total_units' => 'integer',
            'management_fee_bps' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<UnitLinkNavHistory, $this> */
    public function navHistories(): HasMany
    {
        return $this->hasMany(UnitLinkNavHistory::class, 'fund_id');
    }

    /** @return HasMany<UnitLinkHolding, $this> */
    public function holdings(): HasMany
    {
        return $this->hasMany(UnitLinkHolding::class, 'fund_id');
    }
}

