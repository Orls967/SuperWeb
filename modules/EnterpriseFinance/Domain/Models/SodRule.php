<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SodRule extends Model
{
    protected $table = 'ef_sod_rules';

    protected $fillable = [
        'rule_code',
        'role_a',
        'role_b',
        'description',
        'risk_level',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
