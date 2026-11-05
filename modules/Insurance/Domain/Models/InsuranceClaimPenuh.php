<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InsuranceClaimPenuh extends Model
{
    protected $table = 'ins_full_claims';

    protected $fillable = [
        'claim_number',
        'policy_id',
        'incident_date',
        'incident_description',
        'claimed_amount_idr',
        'approved_amount_idr',
        'subrogation_recovered_idr',
        'reinsurance_recovered_idr',
        'net_payout_idr',
        'status',
        'approved_by_user_ref',
        'approved_at',
        'idempotency_key',
    ];

    protected $casts = [
        'incident_date' => 'date',
        'claimed_amount_idr' => 'integer',
        'approved_amount_idr' => 'integer',
        'subrogation_recovered_idr' => 'integer',
        'reinsurance_recovered_idr' => 'integer',
        'net_payout_idr' => 'integer',
        'approved_by_user_ref' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicyPenuh::class, 'policy_id');
    }
}
