<?php

declare(strict_types=1);

namespace Modules\Hcm\Domain\Models\Gig;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovBountyPof extends Model
{
    protected $table = 'hcm_bounty_pofs';

    protected $fillable = [
        'pof_code',
        'bounty_claim_id',
        'actual_hours_worked',
        'overtime_multiplier',
        'geo_location_hash',
        'photo_evidence_ref',
        'supervisor_user_id',
        'calculated_overtime_pay_idr',
        'status',
    ];

    protected $casts = [
        'actual_hours_worked' => 'decimal:2',
        'overtime_multiplier' => 'decimal:2',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(GovBountyClaim::class, 'bounty_claim_id');
    }
}
