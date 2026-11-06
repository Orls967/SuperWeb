<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GridNode extends Model
{
    use HasUuids;

    protected $table = 'egy_grid_nodes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'node_code',
        'name',
        'substation_region',
        'nominal_voltage_kv',
        'current_load_mw',
        'peak_capacity_mw',
    ];

    protected $casts = [
        'nominal_voltage_kv' => 'float',
        'current_load_mw' => 'float',
        'peak_capacity_mw' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->substation_region)) {
                $model->substation_region = 'WEST_REGION';
            }
            if (empty($model->nominal_voltage_kv)) {
                $model->nominal_voltage_kv = 20.0;
            }
            if (! isset($model->current_load_mw)) {
                $model->current_load_mw = 0.0;
            }
            if (empty($model->peak_capacity_mw)) {
                $model->peak_capacity_mw = 100.0;
            }
        });
    }

    public function meters(): HasMany
    {
        return $this->hasMany(SmartMeter::class, 'node_id');
    }
}
