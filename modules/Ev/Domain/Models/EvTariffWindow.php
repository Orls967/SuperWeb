<?php

declare(strict_types=1);

namespace Modules\Ev\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EvTariffWindow extends Model
{
    protected $table = 'oto_ev_tariff_windows';

    protected $fillable = [
        'station_id',
        'name',
        'start_time',
        'end_time',
        'rate_per_kwh_idr',
        'is_cross_midnight',
    ];

    protected $casts = [
        'rate_per_kwh_idr' => 'integer',
        'is_cross_midnight' => 'boolean',
    ];

    public function station()
    {
        return $this->belongsTo(EvStation::class, 'station_id');
    }
}
