<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceClaim extends Model
{
    protected $table = 'ins_claims';

    protected $fillable = [
        'claim_number',
        'policy_id',
        'trigger_event_id',
        'payout_amount_idr',
        'evidence_payload',
        'status',
        'paid_at',
        'idempotency_key',
    ];

    protected $casts = [
        'payout_amount_idr' => 'integer',
        'evidence_payload' => 'array',
        'paid_at' => 'datetime',
    ];

    public function policy()
    {
        return $this->belongsTo(InsurancePolicy::class, 'policy_id');
    }
}
