<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EvCharger extends Model
{
    protected $table = 'oto_ev_chargers';

    protected $fillable = [
        'station_id',
        'charger_code',
        'type',
        'charger_type',
        'max_kw',
        'max_power_kw',
        'status',
    ];

    public function getMaxPowerKwAttribute(): int
    {
        return (int) ($this->attributes['max_power_kw'] ?? $this->attributes['max_kw'] ?? 50);
    }

    public function setMaxPowerKwAttribute($value): void
    {
        $this->attributes['max_kw'] = (int) $value;
    }

    public function setChargerTypeAttribute($value): void
    {
        $this->attributes['type'] = (string) $value;
    }

    public function getChargerTypeAttribute(): string
    {
        return (string) ($this->attributes['type'] ?? 'DC_FAST');
    }

    public function station()
    {
        return $this->belongsTo(EvStation::class, 'station_id');
    }

    public function sessions()
    {
        return $this->hasMany(EvSession::class, 'charger_id');
    }
}
