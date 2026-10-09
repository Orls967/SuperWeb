<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CloudComputeInstance extends Model
{
    protected $table = 'tlx_cloud_compute_instances';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'vcpus' => 'integer',
        'ram_gb' => 'integer',
        'storage_gb' => 'integer',
        'hourly_rate_minor' => 'integer',
        'running_hours_billed' => 'integer',
        'total_chargeback_minor' => 'integer',
    ];
}
