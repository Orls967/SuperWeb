<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\Vehicle;

class EvSession extends Model
{
    protected $table = 'oto_ev_sessions';

    protected $fillable = [
        'session_code',
        'charger_id',
        'vehicle_id',
        'user_id',
        'reserved_from',
        'reserved_to',
        'plugged_in_at',
        'started_at',
        'completed_at',
        'energy_wh',
        'tariff_per_kwh_idr',
        'total_cost_idr',
        'deposit_held_idr',
        'refund_idr',
        'no_show_fee_idr',
        'initial_soh_pct',
        'final_soh_pct',
        'final_soh_bps',
        'peak_cell_temp_c',
        'deferred_minutes',
        'scope2_co2_kg',
        'green_km_saved_co2_kg',
        'status',
    ];

    protected $casts = [
        'reserved_from' => 'datetime',
        'reserved_to' => 'datetime',
        'plugged_in_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'energy_wh' => 'integer',
        'tariff_per_kwh_idr' => 'integer',
        'total_cost_idr' => 'integer',
        'deposit_held_idr' => 'integer',
        'refund_idr' => 'integer',
        'no_show_fee_idr' => 'integer',
        'final_soh_bps' => 'integer',
        'initial_soh_pct' => 'float',
        'final_soh_pct' => 'float',
        'peak_cell_temp_c' => 'float',
        'deferred_minutes' => 'integer',
        'scope2_co2_kg' => 'float',
        'green_km_saved_co2_kg' => 'float',
    ];

    public function charger()
    {
        return $this->belongsTo(EvCharger::class, 'charger_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function meterValues()
    {
        return $this->hasMany(EvMeterValue::class, 'session_id')->orderBy('read_at');
    }
}
