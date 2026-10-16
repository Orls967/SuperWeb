<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PpaContract extends Model
{
    protected $table = 'egy_ppa_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contract_number',
        'producer_entity_id',
        'grid_offtaker_id',
        'feed_in_tariff_per_kwh_minor',
        'surplus_kwh_exported',
        'total_settlement_minor',
        'status',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'feed_in_tariff_per_kwh_minor' => 'float',
        'surplus_kwh_exported' => 'float',
        'total_settlement_minor' => 'integer',
    ];
}
