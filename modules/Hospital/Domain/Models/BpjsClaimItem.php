<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BpjsClaimItem extends Model
{
    protected $table = 'hsp_bpjs_claim_items';

    protected $fillable = [
        'batch_id',
        'billing_episode_id',
        'drg_code',
        'drg_tariff_idr',
        'verification_status',
        'denial_reason',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BpjsClaimBatch::class, 'batch_id');
    }

    public function billingEpisode(): BelongsTo
    {
        return $this->belongsTo(BillingEpisode::class, 'billing_episode_id');
    }
}
