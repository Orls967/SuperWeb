<?php

declare(strict_types=1);

namespace Modules\Fleet\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FleetInvoice extends Model
{
    protected $table = 'oto_fleet_invoices';

    protected $fillable = [
        'invoice_number',
        'contract_id',
        'billing_period',
        'rental_fee_idr',
        'penalty_credit_idr',
        'excess_mileage_fee_idr',
        'total_billed_idr',
        'idempotency_key',
        'status',
    ];

    protected $casts = [
        'rental_fee_idr' => 'integer',
        'penalty_credit_idr' => 'integer',
        'excess_mileage_fee_idr' => 'integer',
        'total_billed_idr' => 'integer',
    ];

    public function contract()
    {
        return $this->belongsTo(FleetContract::class, 'contract_id');
    }
}
