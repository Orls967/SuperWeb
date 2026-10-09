<?php

namespace Modules\Trade\Domain\Models\Bidding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BidRun extends Model
{
    protected $table = 'trd_bid_runs';

    protected $guarded = [];

    protected $casts = [
        'clause_drafts' => 'array',
        'buyer_risk_score' => 'decimal:2',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(BiddingAgent::class, 'bidding_agent_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(BidSubmission::class, 'bid_run_id');
    }
}
