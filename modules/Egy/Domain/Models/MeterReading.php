<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

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
        static::creating(function (self $model): void {
            $readingAt = $model->reading_at ?? CarbonImmutable::now();
            if ($model->kwh_consumed === null) {
                $model->kwh_consumed = ($model->delta_wh ?? 0) / 1000.0;
            }
            if ($model->rate_per_kwh_minor === null) {
                $model->rate_per_kwh_minor = $model->is_wbp ? 2100 : 1450;
            }
            if ($model->total_charge_minor === null) {
                $model->total_charge_minor = (int) round(($model->kwh_consumed ?? 0.0) * $model->rate_per_kwh_minor);
            }
            if ($model->interval_start === null) {
                $model->interval_start = $readingAt;
            }
            if ($model->interval_end === null) {
                $model->interval_end = $readingAt;
            }
        });

        static::updating(function (): void {
            throw new RuntimeException('Record is immutable append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Record is immutable append-only and cannot be deleted.');
        });
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(SmartMeter::class, 'meter_id');
    }
}
