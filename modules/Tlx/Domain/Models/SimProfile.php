<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimProfile extends Model
{
    use HasUuids;

    protected $table = 'tlx_sim_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'iccid',
        'imsi',
        'msisdn',
        'assigned_device_id',
        'plan_code',
        'monthly_quota_mb',
        'status',
    ];

    protected $casts = [
        'monthly_quota_mb' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(IotDevice::class, 'assigned_device_id');
    }
}
