<?php

declare(strict_types=1);

namespace Modules\Ev\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Models\Vehicle;

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
        'completed_at',
        'energy_wh',
        'tariff_per_kwh_idr',
        'total_cost_idr',
        'no_show_fee_idr',
        'initial_soh_pct',
        'final_soh_pct',
        'status',
    ];

    protected $casts = [
        'reserved_from' => 'datetime',
        'reserved_to' => 'datetime',
        'plugged_in_at' => 'datetime',
        'completed_at' => 'datetime',
        'energy_wh' => 'integer',
        'tariff_per_kwh_idr' => 'integer',
        'total_cost_idr' => 'integer',
        'no_show_fee_idr' => 'integer',
        'initial_soh_pct' => 'float',
        'final_soh_pct' => 'float',
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
}
