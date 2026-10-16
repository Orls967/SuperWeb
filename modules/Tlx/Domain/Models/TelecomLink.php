<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TelecomLink extends Model
{
    protected $table = 'tlx_links';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'link_code',
        'origin_site_id',
        'dest_site_id',
        'link_medium',
        'bandwidth_capacity_gbps',
        'allocated_bandwidth_gbps',
        'target_sla_uptime_pct',
        'actual_sla_uptime_pct',
        'downtime_minutes_month',
        'status',
    ];

    protected $casts = [
        'bandwidth_capacity_gbps' => 'float',
        'allocated_bandwidth_gbps' => 'float',
        'target_sla_uptime_pct' => 'float',
        'actual_sla_uptime_pct' => 'float',
        'downtime_minutes_month' => 'integer',
    ];
}
