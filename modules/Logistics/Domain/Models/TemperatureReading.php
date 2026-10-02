<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

/**
 * Cold-chain temperature readings for reefer shipments.
 *
 * An excursion is flagged when the temperature goes outside
 * the min/max range defined on the shipment packages.
 */
class TemperatureReading extends LogisticsEntity
{
    protected $table = 'lgx_temperature_readings';

    protected $fillable = [
        'shipment_id',
        'container_id',
        'temp_c10',
        'min_c10',
        'max_c10',
        'excursion',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'temp_c10' => 'integer',
            'min_c10' => 'integer',
            'max_c10' => 'integer',
            'excursion' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    /**
     * Temperature in °C with one decimal precision.
     */
    public function temperatureCelsius(): float
    {
        return $this->temp_c10 / 10;
    }

    /**
     * Check whether the reading exceeds the defined range.
     */
    public function isExcursion(): bool
    {
        if ($this->min_c10 !== null && $this->temp_c10 < $this->min_c10) {
            return true;
        }

        if ($this->max_c10 !== null && $this->temp_c10 > $this->max_c10) {
            return true;
        }

        return false;
    }
}
