<?php

declare(strict_types=1);

namespace Modules\Hcm\Domain\Models\Gig;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovBounty extends Model
{
    protected $table = 'hcm_bounties';

    protected $fillable = [
        'bounty_code',
        'business_unit_cost_center',
        'title',
        'description',
        'shift_start',
        'shift_end',
        'duration_hours',
        'hourly_rate_idr',
        'required_certification',
        'requires_high_risk_permit',
        'embedded_insurance_policy_ref',
        'speed_bonus_idr',
        'unit_rating_score',
        'status',
    ];

    protected $casts = [
        'shift_start' => 'datetime',
        'shift_end' => 'datetime',
        'duration_hours' => 'decimal:2',
        'requires_high_risk_permit' => 'boolean',
        'unit_rating_score' => 'decimal:2',
    ];

    public function claims(): HasMany
    {
        return $this->hasMany(GovBountyClaim::class, 'bounty_id');
    }
}
