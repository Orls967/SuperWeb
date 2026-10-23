<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EscoPerformanceContract extends Model
{
    protected $table = 'egy_esco_performance_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contract_number',
        'client_property_id',
        'baseline_energy_cost_minor',
        'actual_energy_cost_minor',
        'verified_savings_minor',
        'esco_share_pct',
        'esco_remuneration_minor',
        'client_retained_saving_minor',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'baseline_energy_cost_minor' => 'integer',
        'actual_energy_cost_minor' => 'integer',
        'verified_savings_minor' => 'integer',
        'esco_share_pct' => 'float',
        'esco_remuneration_minor' => 'integer',
        'client_retained_saving_minor' => 'integer',
    ];
}
