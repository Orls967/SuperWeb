<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Insurance\Domain\Concerns\AppendOnly;

class CatBondCouponPayout extends Model
{
    use AppendOnly;

    protected $table = 'ins_cat_bond_coupon_payouts';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'coupon_id',
        'holder_user_id',
        'units',
        'amount_idr',
    ];
}
