<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EvBatteryPassport extends Model
{
    protected $table = 'oto_ev_battery_passports';

    protected $fillable = [
        'vehicle_id',
        'session_code',
        'cycle_number',
        'initial_soh_pct',
        'final_soh_pct',
        'delta_soh',
        'max_cell_temp_c',
        'energy_wh',
        'prev_hash',
        'block_hash',
        'recorded_at',
    ];

    protected $casts = [
        'cycle_number' => 'integer',
        'initial_soh_pct' => 'float',
        'final_soh_pct' => 'float',
        'delta_soh' => 'float',
        'max_cell_temp_c' => 'float',
        'energy_wh' => 'integer',
        'recorded_at' => 'datetime',
    ];
}
