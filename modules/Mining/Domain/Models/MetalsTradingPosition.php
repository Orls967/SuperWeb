<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MetalsTradingPosition extends Model
{
    protected $table = 'min_metals_trading_positions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'tonnage' => 'float',
        'entry_price_usd' => 'decimal:2',
        'current_mark_price_usd' => 'decimal:2',
        'unrealized_pnl_minor' => 'integer',
    ];
}
