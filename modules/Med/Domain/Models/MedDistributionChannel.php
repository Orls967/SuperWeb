<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedDistributionChannel extends Model
{
    protected $table = 'med_distribution_channels';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'channel_code',
        'name',
        'channel_type',
        'revenue_share_pct',
        'status',
    ];

    protected $casts = [
        'revenue_share_pct' => 'float',
    ];
}
