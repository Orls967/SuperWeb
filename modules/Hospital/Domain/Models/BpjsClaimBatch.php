<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BpjsClaimBatch extends Model
{
    protected $table = 'hsp_bpjs_claim_batches';

    protected $fillable = [
        'batch_number',
        'claim_month',
        'total_episodes',
        'total_claimed_idr',
        'approved_amount_idr',
        'denied_amount_idr',
        'status',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BpjsClaimItem::class, 'batch_id');
    }
}
