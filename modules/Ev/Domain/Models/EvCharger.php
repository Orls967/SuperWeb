<?php

declare(strict_types=1);

namespace Modules\Ev\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EvCharger extends Model
{
    protected $table = 'oto_ev_chargers';

    protected $fillable = [
        'station_id',
        'charger_code',
        'type',
        'max_kw',
        'status',
    ];

    public function station()
    {
        return $this->belongsTo(EvStation::class, 'station_id');
    }

    public function sessions()
    {
        return $this->hasMany(EvSession::class, 'charger_id');
    }
}
