<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthMembership extends Model
{
    protected $table = 'hsp_memberships';

    protected $fillable = [
        'membership_number',
        'patient_id',
        'tier',
        'annual_wellness_credits_pts',
        'remaining_credits_pts',
        'expires_at',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
