<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class NocNetworkTicket extends Model
{
    protected $table = 'tlx_noc_network_tickets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'mttr_minutes' => 'integer',
        'raised_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
