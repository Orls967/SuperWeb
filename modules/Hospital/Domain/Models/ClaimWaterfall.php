<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimWaterfall extends Model
{
    protected $table = 'hsp_claim_waterfalls';

    protected $fillable = [
        'billing_episode_id',
        'step_order',
        'payer_type',
        'payer_reference',
        'gross_billed_idr',
        'deductions_idr',
        'covered_amount_idr',
        'remaining_balance_idr',
        'status',
        'settled_at',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'gross_billed_idr' => 'integer',
        'deductions_idr' => 'integer',
        'covered_amount_idr' => 'integer',
        'remaining_balance_idr' => 'integer',
        'settled_at' => 'datetime',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(BillingEpisode::class, 'billing_episode_id');
    }
}
