<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class NocNetworkTicket extends Model
{
    protected $table = 'tlx_noc_network_tickets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'ticket_number',
        'idempotency_alarm_key',
        'link_id',
        'site_id',
        'alarm_type',
        'severity',
        'correlated_line_incident_id',
        'mttr_minutes',
        'status',
        'raised_at',
        'resolved_at',
    ];

    protected $casts = [
        'mttr_minutes' => 'integer',
        'raised_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
