<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceParametricOracle extends Model
{
    protected $table = 'ins_parametric_oracles';

    protected $fillable = [
        'oracle_code',
        'oracle_name',
        'source_type',
        'metric_name',
        'condition_operator',
        'threshold_value',
        'is_active',
    ];

    protected $casts = [
        'threshold_value' => 'integer',
        'is_active' => 'boolean',
    ];
}
