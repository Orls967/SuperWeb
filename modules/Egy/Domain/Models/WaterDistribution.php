<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class WaterDistribution extends Model
{
    protected $table = 'egy_water_distributions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'input_volume_m3' => 'float',
        'leakage_loss_m3' => 'float',
        'delivered_volume_m3' => 'float',
        'water_tariff_minor' => 'integer',
        'water_charge_minor' => 'integer',
    ];
}
