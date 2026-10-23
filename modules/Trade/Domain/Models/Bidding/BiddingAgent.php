<?php

declare(strict_types=1);

namespace Modules\Trade\Domain\Models\Bidding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiddingAgent extends Model
{
    protected $table = 'trd_bidding_agents';

    protected $fillable = [
        'agent_code',
        'entity_code',
        'commodity_code',
        'min_price_floor_idr',
        'max_price_ceiling_idr',
        'target_margin_pct',
        'max_risk_score',
        'status',
    ];

    protected $casts = [
        'target_margin_pct' => 'decimal:2',
        'max_risk_score' => 'decimal:2',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(BidRun::class, 'bidding_agent_id');
    }
}
