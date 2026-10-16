<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MetalsTradingPosition extends Model
{
    protected $table = 'min_metals_trading_positions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'desk_code',
        'commodity',
        'position_type',
        'tonnage',
        'entry_price_usd',
        'current_mark_price_usd',
        'unrealized_pnl_minor',
        'status',
    ];

    protected $casts = [
        'tonnage' => 'float',
        'entry_price_usd' => 'decimal:2',
        'current_mark_price_usd' => 'decimal:2',
        'unrealized_pnl_minor' => 'integer',
    ];
}
