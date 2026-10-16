<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CarbonCreditIssuance extends Model
{
    protected $table = 'min_carbon_credit_issuances';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'project_code',
        'site_id',
        'project_type',
        'verified_ndvi_score',
        'verified_carbon_tons',
        'issued_credits_tons',
        'registry_serial_number',
        'status',
    ];

    protected $casts = [
        'verified_ndvi_score' => 'float',
        'verified_carbon_tons' => 'float',
        'issued_credits_tons' => 'float',
    ];
}
