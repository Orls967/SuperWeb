<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class AviationExternalFactor extends Model
{
    protected $table = 'avi_external_factors';

    protected $fillable = [
        'factor_key',
        'factor_value',
        'effective_from',
        'source',
        'label',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
    ];
}

