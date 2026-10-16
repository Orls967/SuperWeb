<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ColocationContract extends Model
{
    protected $table = 'tlx_colocation_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contract_number',
        'tenant_party_id',
        'dc_id',
        'rack_units_allocated',
        'monthly_rack_fee_minor',
        'power_rate_per_kwh_minor',
        'monthly_kwh_consumed',
        'port_status',
        'is_overdue',
    ];

    protected $casts = [
        'rack_units_allocated' => 'integer',
        'monthly_rack_fee_minor' => 'integer',
        'power_rate_per_kwh_minor' => 'integer',
        'monthly_kwh_consumed' => 'float',
        'is_overdue' => 'boolean',
    ];
}
