<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Insurance\Domain\Concerns\AppendOnly;

class CatBondCoupon extends Model
{
    use AppendOnly;

    protected $table = 'ins_cat_bond_coupons';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'bond_id',
        'quarter_key',
        'outstanding_idr',
        'total_idr',
        'ledger_transaction_id',
        'paid_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    /** @return HasMany<CatBondCouponPayout, $this> */
    public function payouts(): HasMany
    {
        return $this->hasMany(CatBondCouponPayout::class, 'coupon_id')->orderBy('holder_user_id');
    }
}
