<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IotDevice extends Model
{
    use HasUuids;

    protected $table = 'tlx_iot_devices';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'device_uuid',
        'device_code',
        'device_type',
        'owner_entity_id',
        'using_module',
        'active_sim_iccid',
        'data_quota_mb_monthly',
        'consumed_mb_monthly',
        'rate_per_mb_minor',
        'monthly_fee_minor',
        'idempotency_key',
        'status',
        'activated_at',
        'suspended_at',
        'retired_at',
    ];

    protected $casts = [
        'data_quota_mb_monthly' => 'float',
        'consumed_mb_monthly' => 'float',
        'rate_per_mb_minor' => 'integer',
        'monthly_fee_minor' => 'integer',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'retired_at' => 'datetime',
    ];

    public function simProfile(): HasOne
    {
        return $this->hasOne(SimProfile::class, 'assigned_device_id');
    }
}
