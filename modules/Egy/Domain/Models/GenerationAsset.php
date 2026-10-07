<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class GenerationAsset extends Model
{
    protected $table = 'egy_generation_assets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'installed_capacity_mw' => 'float',
        'current_output_mw' => 'float',
        'marginal_cost_per_mwh_minor' => 'integer',
    ];
}
