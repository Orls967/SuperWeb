<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybParallelSalamHedge extends Model
{
    protected $table = 'syb_parallel_salam_hedges';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'salam_id',
        'hedge_contract_number',
        'offtaker_party_ref',
        'agreed_sale_price_cents',
        'quantity_hedged',
        'delivery_date',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'agreed_sale_price_cents' => 'integer',
        'quantity_hedged' => 'integer',
        'delivery_date' => 'date',
    ];

    /**
     * @return BelongsTo<SybSalamContract, $this>
     */
    public function salamContract(): BelongsTo
    {
        return $this->belongsTo(SybSalamContract::class, 'salam_id');
    }
}
