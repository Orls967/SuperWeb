<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterReading extends Model
{
    use HasUuids;

    protected $table = 'egy_meter_readings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'meter_id',
        'reading_at',
        'interval_start',
        'interval_end',
        'consumption_kwh',
        'production_kwh',
        'peak_kw',
        'power_factor_bps',
        'kwh_consumed',
        'cumulative_wh',
        'delta_wh',
        'interval_status',
        'is_wbp',
        'is_peak_hour',
        'rate_per_kwh_minor',
        'total_charge_minor',
        'is_reversal',
        'reversal_for_id',
        'idempotency_key',
    ];

    protected $casts = [
        'interval_start' => 'datetime',
        'interval_end' => 'datetime',
        'reading_at' => 'datetime',
        'consumption_kwh' => 'integer',
        'production_kwh' => 'integer',
        'peak_kw' => 'integer',
        'power_factor_bps' => 'integer',
        'cumulative_wh' => 'integer',
        'delta_wh' => 'integer',
        'is_peak_hour' => 'boolean',
        'is_wbp' => 'boolean',
        'is_reversal' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->kwh_consumed)) {
                $model->kwh_consumed = (float) ($model->consumption_kwh ?? 0);
            }
            if (empty($model->consumption_kwh)) {
                $model->consumption_kwh = (int) ($model->kwh_consumed ?? 0);
            }
            if (empty($model->rate_per_kwh_minor)) {
                $model->rate_per_kwh_minor = 144470;
            }
            if (! isset($model->total_charge_minor)) {
                $model->total_charge_minor = (int) ($model->kwh_consumed * $model->rate_per_kwh_minor / 100);
            }
            if (empty($model->reading_at)) {
                $model->reading_at = $model->interval_end ?? $model->interval_start ?? now();
            }
            if (empty($model->interval_start)) {
                $model->interval_start = now()->subMinutes(15);
            }
            if (empty($model->interval_end)) {
                $model->interval_end = now();
            }
        });
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(SmartMeter::class, 'meter_id');
    }
}
