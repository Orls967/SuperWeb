<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AgriInsuranceClaim extends Model
{
    protected $table = 'ins_agri_claims';

    protected $fillable = [
        'claim_number',
        'policy_id',
        'actual_index_val',
        'gross_claim_idr',
        'loan_offset_amount_idr',
        'net_farmer_payout_idr',
        'status',
        'adjudicated_at',
    ];

    protected $casts = [
        'actual_index_val' => 'integer',
        'gross_claim_idr' => 'integer',
        'loan_offset_amount_idr' => 'integer',
        'net_farmer_payout_idr' => 'integer',
        'adjudicated_at' => 'datetime',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(AgriInsurancePolicy::class, 'policy_id');
    }
}
