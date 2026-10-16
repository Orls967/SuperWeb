<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class InterconnectSettlement extends Model
{
    protected $table = 'tlx_interconnect_settlements';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'settlement_batch_code',
        'partner_operator_code',
        'period_month',
        'inbound_minutes',
        'outbound_minutes',
        'inbound_receivable_minor',
        'outbound_payable_minor',
        'net_settlement_minor',
        'status',
    ];

    protected $casts = [
        'inbound_minutes' => 'integer',
        'outbound_minutes' => 'integer',
        'inbound_receivable_minor' => 'integer',
        'outbound_payable_minor' => 'integer',
        'net_settlement_minor' => 'integer',
    ];
}
