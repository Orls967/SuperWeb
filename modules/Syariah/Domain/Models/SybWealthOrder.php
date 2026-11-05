<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybWealthOrder extends Model
{
    protected $table = 'syb_wealth_orders';

    protected $fillable = [
        'portfolio_id',
        'instrument_id',
        'order_type',
        'units',
        'amount_idr',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'portfolio_id' => 'integer',
        'instrument_id' => 'integer',
        'units' => 'integer',
        'amount_idr' => 'integer',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(SybWealthPortfolio::class, 'portfolio_id');
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(SybWealthInstrument::class, 'instrument_id');
    }
}
