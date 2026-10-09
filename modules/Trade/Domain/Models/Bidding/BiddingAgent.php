<?php

namespace Modules\Trade\Domain\Models\Bidding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiddingAgent extends Model
{
    protected $table = 'trd_bidding_agents';

    protected $guarded = [];

    protected $casts = [
        'target_margin_pct' => 'decimal:2',
        'max_risk_score' => 'decimal:2',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(BidRun::class, 'bidding_agent_id');
    }
}
