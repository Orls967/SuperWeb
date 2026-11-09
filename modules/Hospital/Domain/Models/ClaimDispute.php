<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimDispute extends Model
{
    protected $table = 'hsp_claim_disputes';

    protected $fillable = [
        'dispute_number',
        'billing_episode_id',
        'claim_reference',
        'dispute_reason_code',
        'disputed_amount_idr',
        'reconciled_amount_idr',
        'status',
        'hospital_justification',
        'payer_verdict',
        'resolved_at',
    ];

    protected $casts = [
        'disputed_amount_idr' => 'integer',
        'reconciled_amount_idr' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(BillingEpisode::class, 'billing_episode_id');
    }
}
