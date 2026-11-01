<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColocationInvoice extends Model
{
    use HasUuids;

    protected $table = 'tlx_colocation_invoices';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'invoice_number',
        'contract_id',
        'tenant_party_id',
        'billing_period',
        'rack_fee_minor',
        'committed_power_charge_minor',
        'excess_power_charge_minor',
        'total_minor',
        'idempotency_key',
        'status',
    ];

    protected $casts = [
        'rack_fee_minor' => 'integer',
        'committed_power_charge_minor' => 'integer',
        'excess_power_charge_minor' => 'integer',
        'total_minor' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ColocationContract::class, 'contract_id');
    }
}
