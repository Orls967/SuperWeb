<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceSlo extends Model
{
    protected $table = 'plt_service_slos';

    protected $fillable = [
        'service_name',
        'tier',
        'target_sli_bps',
        'error_budget_mins',
        'burn_rate_1h',
        'burn_rate_6h',
        'status',
    ];

    protected $casts = [
        'burn_rate_1h' => 'float',
        'burn_rate_6h' => 'float',
    ];
}

