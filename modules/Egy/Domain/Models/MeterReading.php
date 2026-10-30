<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Banking\Domain\Exceptions\ImmutableRecordException;

class MeterReading extends Model
{
    protected $table = 'egy_meter_readings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'meter_id',
        'reading_at',
        'cumulative_wh',
        'delta_wh',
        'interval_status',
        'is_wbp',
        'kwh_consumed',
        'is_peak_hour',
        'rate_per_kwh_minor',
        'total_charge_minor',
        'interval_start',
        'interval_end',
    ];

    protected $casts = [
        'reading_at' => 'datetime',
        'cumulative_wh' => 'integer',
        'delta_wh' => 'integer',
        'interval_status' => 'string',
        'is_wbp' => 'boolean',
        'kwh_consumed' => 'float',
        'is_peak_hour' => 'boolean',
        'rate_per_kwh_minor' => 'integer',
        'total_charge_minor' => 'integer',
        'interval_start' => 'datetime',
        'interval_end' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new ImmutableRecordException(self::class, 'update');
        });

        static::deleting(function (): void {
            throw new ImmutableRecordException(self::class, 'delete');
        });
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(SmartMeter::class, 'meter_id');
    }
}
