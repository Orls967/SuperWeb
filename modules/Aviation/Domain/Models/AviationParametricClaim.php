<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationParametricClaim extends Model
{
    protected $table = 'avi_parametric_claims';

    protected $fillable = [
        'disruption_case_id',
        'policy_number',
        'passenger_party_ref',
        'trigger_type',
        'payout_amount_minor_unit',
        'claim_status',
        'external_reference',
    ];

    protected $casts = [
        'payout_amount_minor_unit' => 'integer',
    ];

    public function disruptionCase(): BelongsTo
    {
        return $this->belongsTo(AviationDisruptionCase::class, 'disruption_case_id');
    }
}
