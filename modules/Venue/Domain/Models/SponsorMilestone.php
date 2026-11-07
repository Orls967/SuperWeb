<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorMilestone extends Model
{
    protected $table = 'ven_sponsor_milestones';

    protected $fillable = [
        'deal_id',
        'milestone_name',
        'due_date',
        'target_impressions',
        'delivered_impressions',
        'payout_ratio_bps',
        'milestone_budget_idr',
        'milestone_payout_idr',
        'status',
    ];

    protected $casts = [
        'due_date' => 'date',
        'target_impressions' => 'integer',
        'delivered_impressions' => 'integer',
        'payout_ratio_bps' => 'integer',
        'milestone_budget_idr' => 'integer',
        'milestone_payout_idr' => 'integer',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(SponsorDeal::class, 'deal_id');
    }
}
