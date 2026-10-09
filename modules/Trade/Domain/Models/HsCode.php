<?php

declare(strict_types=1);

namespace Modules\Trade\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HsCode extends Model
{
    use HasUuids;

    protected $table = 'trd_hs_codes';

    protected $fillable = [
        'hs_code',
        'description',
        'base_duty_rate_percent',
        'fta_preferential_rate_percent',
        'is_lartas',
        'lartas_permit_required',
    ];

    protected $casts = [
        'base_duty_rate_percent' => 'float',
        'fta_preferential_rate_percent' => 'float',
        'is_lartas' => 'boolean',
    ];
}
