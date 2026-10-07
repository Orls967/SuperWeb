<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EscoPerformanceContract extends Model
{
    protected $table = 'egy_esco_performance_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'baseline_energy_cost_minor' => 'integer',
        'actual_energy_cost_minor' => 'integer',
        'verified_savings_minor' => 'integer',
        'esco_share_pct' => 'float',
        'esco_remuneration_minor' => 'integer',
        'client_retained_saving_minor' => 'integer',
    ];
}
