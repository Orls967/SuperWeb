<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedIpLicense extends Model
{
    protected $table = 'med_ip_licenses';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'license_code',
        'ip_id',
        'licensee_entity_id',
        'channel',
        'territory',
        'start_date',
        'end_date',
        'royalty_rate_pct',
        'minimum_guarantee_minor',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'royalty_rate_pct' => 'float',
        'minimum_guarantee_minor' => 'integer',
    ];
}
