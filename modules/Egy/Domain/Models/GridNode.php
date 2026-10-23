<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class GridNode extends Model
{
    protected $table = 'egy_grid_nodes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'node_code',
        'name',
        'substation_region',
        'nominal_voltage_kv',
        'current_load_mw',
        'peak_capacity_mw',
    ];

    protected $casts = [
        'nominal_voltage_kv' => 'float',
        'current_load_mw' => 'float',
        'peak_capacity_mw' => 'float',
    ];
}
