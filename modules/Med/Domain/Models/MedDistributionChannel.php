<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedDistributionChannel extends Model
{
    protected $table = 'med_distribution_channels';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'revenue_share_pct' => 'float',
    ];
}
