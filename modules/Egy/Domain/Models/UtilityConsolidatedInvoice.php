<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UtilityConsolidatedInvoice extends Model
{
    protected $table = 'egy_utility_consolidated_invoices';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'invoice_number',
        'property_id',
        'billing_period',
        'total_wh',
        'electricity_charge_minor',
        'water_charge_minor',
        'district_cooling_charge_minor',
        'gas_charge_minor',
        'total_consolidated_minor',
        'status',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'total_wh' => 'integer',
        'electricity_charge_minor' => 'integer',
        'water_charge_minor' => 'integer',
        'district_cooling_charge_minor' => 'integer',
        'gas_charge_minor' => 'integer',
        'total_consolidated_minor' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(UtilityInvoiceLine::class, 'invoice_id');
    }
}
