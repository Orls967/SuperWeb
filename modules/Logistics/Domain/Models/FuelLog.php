<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends LogisticsEntity
{
    protected $table = 'lgx_fuel_logs';

    protected $fillable = [
        'truck_id',
        'driver_id',
        'schedule_id',
        'liters_x1000',
        'price_per_liter_idr',
        'total_cost_idr',
        'odometer_m',
        'previous_odometer_m',
        'distance_m',
        'km_per_liter_x100',
        'baseline_km_per_liter_x100',
        'deviation_bp',
        'is_anomaly',
        'anomaly_note',
        'recorded_by',
        'filled_at',
    ];

    protected function casts(): array
    {
        return [
            'liters_x1000' => 'integer',
            'price_per_liter_idr' => 'integer',
            'total_cost_idr' => 'integer',
            'odometer_m' => 'integer',
            'previous_odometer_m' => 'integer',
            'distance_m' => 'integer',
            'km_per_liter_x100' => 'integer',
            'baseline_km_per_liter_x100' => 'integer',
            'deviation_bp' => 'integer',
            'is_anomaly' => 'boolean',
            'filled_at' => 'datetime',
        ];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class, 'truck_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function litersFormatted(): string
    {
        return number_format($this->liters_x1000 / 1000, 3, ',', '.');
    }
}
