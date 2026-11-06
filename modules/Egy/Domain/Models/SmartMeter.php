<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmartMeter extends Model
{
    use HasUuids;

    protected $table = 'egy_smart_meters';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'meter_code',
        'utility_type',
        'property_id',
        'node_id',
        'customer_party_id',
        'is_active',
        'meter_serial_number',
        'consumer_property_type',
        'consumer_property_id',
        'grid_node_id',
        'total_kwh_accumulated',
        'tariff_category',
        'modulus',
        'device_token',
        'status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'total_kwh_accumulated' => 'float',
        'modulus' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->meter_serial_number)) {
                $model->meter_serial_number = $model->meter_code ?? ('MTR-'.uniqid());
            }
            if (empty($model->meter_code)) {
                $model->meter_code = $model->meter_serial_number;
            }
            if (empty($model->consumer_property_type)) {
                $model->consumer_property_type = 'BUILDING';
            }
            if (empty($model->consumer_property_id)) {
                $model->consumer_property_id = $model->property_id ?? 'PROP-DEFAULT';
            }
            if (empty($model->property_id)) {
                $model->property_id = $model->consumer_property_id;
            }
            if (empty($model->utility_type)) {
                $model->utility_type = 'electricity';
            }
            if (empty($model->status)) {
                $model->status = $model->is_active ? 'ACTIVE' : 'INACTIVE';
            }
        });
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class, 'meter_id');
    }
}
