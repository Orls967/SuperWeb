<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketCommission extends Model
{
    protected $table = 'ins_market_commissions';

    /** @var list<string> */
    protected $fillable = [
        'broker_id',
        'embedded_policy_id',
        'premium_idr',
        'commission_bps',
        'commission_amount_idr',
        'status',
        'ledger_transaction_id',
        'settled_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'premium_idr' => 'integer',
            'commission_bps' => 'integer',
            'commission_amount_idr' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MarketBroker, $this> */
    public function broker(): BelongsTo
    {
        return $this->belongsTo(MarketBroker::class, 'broker_id');
    }

    /** @return BelongsTo<EmbedPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(EmbedPolicy::class, 'embedded_policy_id');
    }
}
