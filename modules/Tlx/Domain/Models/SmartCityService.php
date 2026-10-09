<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SmartCityService extends Model
{
    protected $table = 'tlx_smart_city_services';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'active_sensor_count' => 'integer',
        'sla_target_pct' => 'float',
        'sla_achieved_pct' => 'float',
        'monthly_contract_value_minor' => 'integer',
    ];
}
