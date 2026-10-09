<?php

namespace Modules\TradeFinance\Domain\Models\CrossBorder;

use Illuminate\Database\Eloquent\Model;

class CrossBorderEscrow extends Model
{
    protected $table = 'tf_crossborder_escrows';

    protected $guarded = [];

    protected $casts = [
        'released_at' => 'datetime',
    ];
}
