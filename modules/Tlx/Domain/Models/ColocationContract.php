<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColocationContract extends Model
{
    use HasUuids;

    protected $table = 'tlx_colocation_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contract_number',
        'tenant_party_id',
        'dc_id',
        'rack_units_allocated',
        'power_commit_kw',
        'monthly_rack_fee_minor',
        'power_rate_per_kwh_minor',
        'excess_power_rate_per_kwh_minor',
        'monthly_kwh_consumed',
        'port_status',
        'is_overdue',
        'idempotency_key',
    ];

    protected $casts = [
        'rack_units_allocated' => 'integer',
        'power_commit_kw' => 'integer',
        'monthly_rack_fee_minor' => 'integer',
        'power_rate_per_kwh_minor' => 'integer',
        'excess_power_rate_per_kwh_minor' => 'integer',
        'monthly_kwh_consumed' => 'float',
        'is_overdue' => 'boolean',
    ];

    public function dataCenter(): BelongsTo
    {
        return $this->belongsTo(TelecomDataCenter::class, 'dc_id');
    }
}
