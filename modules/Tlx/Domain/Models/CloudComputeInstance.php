<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CloudComputeInstance extends Model
{
    protected $table = 'tlx_cloud_compute_instances';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'instance_code',
        'consumer_entity_id',
        'sku_flavor',
        'vcpus',
        'ram_gb',
        'storage_gb',
        'hourly_rate_minor',
        'running_hours_billed',
        'total_chargeback_minor',
        'status',
    ];

    protected $casts = [
        'vcpus' => 'integer',
        'ram_gb' => 'integer',
        'storage_gb' => 'integer',
        'hourly_rate_minor' => 'integer',
        'running_hours_billed' => 'integer',
        'total_chargeback_minor' => 'integer',
    ];
}
