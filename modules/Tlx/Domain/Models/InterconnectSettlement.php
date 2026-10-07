<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class InterconnectSettlement extends Model
{
    protected $table = 'tlx_interconnect_settlements';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'inbound_minutes' => 'integer',
        'outbound_minutes' => 'integer',
        'inbound_receivable_minor' => 'integer',
        'outbound_payable_minor' => 'integer',
        'net_settlement_minor' => 'integer',
    ];
}
