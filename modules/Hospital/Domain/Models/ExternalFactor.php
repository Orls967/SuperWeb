<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalFactor extends Model
{
    protected $table = 'hsp_external_factors';

    protected $fillable = [
        'factor_key',
        'factor_name',
        'city_code',
        'factor_value',
        'effective_from',
        'source_ref',
        'classification',
    ];

    protected $casts = [
        'factor_value' => 'integer',
        'effective_from' => 'date',
    ];
}
