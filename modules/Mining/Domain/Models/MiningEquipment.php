<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiningEquipment extends Model
{
    use HasUuids;

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

    public function telemetries(): HasMany
    {
        return $this->hasMany(MiningEquipmentTelemetry::class, 'equipment_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }
}
