<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TelecomDataCenter extends Model
{
    protected $table = 'tlx_data_centers';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'dc_code',
        'name',
        'city',
        'total_facility_power_kw',
        'it_load_power_kw',
        'measured_pue',
        'total_racks_capacity',
        'occupied_racks',
    ];

    protected $casts = [
        'total_facility_power_kw' => 'float',
        'it_load_power_kw' => 'float',
        'measured_pue' => 'float',
        'total_racks_capacity' => 'integer',
        'occupied_racks' => 'integer',
    ];
}
