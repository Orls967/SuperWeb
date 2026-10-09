<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class UtilityConsolidatedInvoice extends Model
{
    protected $table = 'egy_utility_consolidated_invoices';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'electricity_charge_minor' => 'integer',
        'water_charge_minor' => 'integer',
        'district_cooling_charge_minor' => 'integer',
        'gas_charge_minor' => 'integer',
        'total_consolidated_minor' => 'integer',
    ];
}
