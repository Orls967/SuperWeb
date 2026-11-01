<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningEquipment extends Model
{
    protected $table = 'min_equipment';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'equipment_code',
        'type',
        'model',
        'capacity_ton',
        'tare_weight_kg',
        'capacity_kg',
        'status',
        'engine_hours',
    ];

    protected $casts = [
        'capacity_ton' => 'decimal:2',
        'tare_weight_kg' => 'integer',
        'capacity_kg' => 'integer',
        'engine_hours' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if ($model->capacity_ton === null && $model->capacity_kg !== null) {
                $model->capacity_ton = round($model->capacity_kg / 1000, 2);
            }
        });
    }
}
