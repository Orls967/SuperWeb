<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetSellerSettlement extends Model
{
    protected $table = 'ret_seller_settlements';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'verified_gmv_minor' => 'integer',
        'commission_rate_pct' => 'float',
        'commission_fee_minor' => 'integer',
        'net_payout_minor' => 'integer',
    ];
}
