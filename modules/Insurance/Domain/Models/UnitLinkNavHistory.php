<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitLinkNavHistory extends Model
{
    protected $table = 'ins_lh_unit_link_nav_histories';

    /** @var list<string> */
    protected $fillable = [
        'fund_id',
        'valuation_date',
        'nav_per_unit_idr',
        'total_aum_idr',
        'total_units',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fund_id' => 'integer',
            'valuation_date' => 'date',
            'nav_per_unit_idr' => 'integer',
            'total_aum_idr' => 'integer',
            'total_units' => 'integer',
        ];
    }

    /** @return BelongsTo<UnitLinkFund, $this> */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(UnitLinkFund::class, 'fund_id');
    }
}

