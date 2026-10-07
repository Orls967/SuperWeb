<?php

namespace Modules\Trade\Domain\Models\Bidding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidSubmission extends Model
{
    protected $table = 'trd_bid_submissions';

    protected $guarded = [];

    protected $casts = [
        'requires_four_eyes' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(BidRun::class, 'bid_run_id');
    }
}
