<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ColocationContract extends Model
{
    protected $table = 'tlx_colocation_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'rack_units_allocated' => 'integer',
        'monthly_rack_fee_minor' => 'integer',
        'power_rate_per_kwh_minor' => 'integer',
        'monthly_kwh_consumed' => 'float',
        'is_overdue' => 'boolean',
    ];
}
