<?php

declare(strict_types=1);

namespace Modules\Trade\Domain\Models\Bidding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidSubmission extends Model
{
    protected $table = 'trd_bid_submissions';

    protected $fillable = [
        'submission_code',
        'bid_run_id',
        'submitted_bid_price_idr',
        'requires_four_eyes',
        'approved_by_user_id',
        'approved_at',
        'status',
        'created_contract_code',
        'feedback_reason',
    ];

    protected $casts = [
        'requires_four_eyes' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(BidRun::class, 'bid_run_id');
    }
}
