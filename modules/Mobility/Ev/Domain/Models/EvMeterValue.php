<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EvMeterValue extends Model
{
    protected $table = 'oto_ev_meter_values';

    protected $fillable = [
        'session_id',
        'read_at',
        'energy_wh',
        'power_w',
        'idempotency_key',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'energy_wh' => 'integer',
        'power_w' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('EvMeterValue is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new \LogicException('EvMeterValue is append-only and cannot be deleted.');
        });
    }

    public function session()
    {
        return $this->belongsTo(EvSession::class, 'session_id');
    }
}
