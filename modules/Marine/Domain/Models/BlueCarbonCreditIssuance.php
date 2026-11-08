<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlueCarbonCreditIssuance extends Model
{
    protected $table = 'mar_blue_carbon_credit_issuances';

    protected $fillable = [
        'serial_number',
        'assessment_id',
        'vintage_year',
        'carbon_credits_tco2e',
        'unit_credit_price_minor_cents',
        'credit_standard',
        'esg_credit_registry_reference',
        'status',
        'issued_at',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(BlueCarbonAssessment::class, 'assessment_id');
    }
}
