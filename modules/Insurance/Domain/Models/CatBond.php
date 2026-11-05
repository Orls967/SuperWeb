<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Insurance\Domain\Enums\CatBondStatus;

class CatBond extends Model
{
    protected $table = 'ins_cat_bonds';

    /** @var list<string> */
    protected $fillable = [
        'bond_code',
        'asset_code',
        'rwa_asset_id',
        'principal_idr',
        'outstanding_principal_idr',
        'units',
        'spread_bps',
        'attachment_idr',
        'exhaustion_idr',
        'cat_run_id',
        'status',
        'issue_date',
        'maturity_date',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CatBondStatus::class,
            'issue_date' => 'date',
            'maturity_date' => 'date',
        ];
    }

    /** @return HasMany<CatBondCoupon, $this> */
    public function coupons(): HasMany
    {
        return $this->hasMany(CatBondCoupon::class, 'bond_id')->orderBy('quarter_key');
    }

    /** @return HasMany<CatBondEvent, $this> */
    public function triggerEvents(): HasMany
    {
        return $this->hasMany(CatBondEvent::class, 'bond_id')->orderBy('id');
    }
}
