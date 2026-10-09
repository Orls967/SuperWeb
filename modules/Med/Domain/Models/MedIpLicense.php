<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedIpLicense extends Model
{
    protected $table = 'med_ip_licenses';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'royalty_rate_pct' => 'float',
        'minimum_guarantee_minor' => 'integer',
    ];
}
