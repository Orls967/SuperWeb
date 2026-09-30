<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Domain\Enums\PaymentTerms;

class ShipperAccount extends LogisticsEntity
{
    protected $table = 'lgx_shipper_accounts';

    protected $fillable = [
        'shipper_id',
        'credit_limit_idr',
        'payment_terms_days',
        'is_active',
    ];

    protected $casts = [
        'shipper_id' => 'integer',
        'credit_limit_idr' => 'integer',
        'payment_terms_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    /**
     * Calculate current total outstanding debt (unpaid invoices + un-invoiced postpaid shipments).
     */
    public function calculateOutstandingBalance(): int
    {
        // 1. Unpaid balance from issued invoices
        $unpaidInvoices = (int) LogisticsInvoice::where('shipper_id', $this->shipper_id)
            ->where('status', 'unpaid')
            ->selectRaw('SUM(total_amount_idr - paid_amount_idr) as total_unpaid')
            ->value('total_unpaid');

        // 2. Un-invoiced postpaid shipments
        $uninvoicedShipments = (int) Shipment::where('shipper_id', $this->shipper_id)
            ->where('payment_terms', PaymentTerms::Postpaid)
            ->whereNull('invoice_id')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->sum('total_amount_idr');

        return $unpaidInvoices + $uninvoicedShipments;
    }

    public function canAccommodate(int $newAmountIdr): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return ($this->calculateOutstandingBalance() + $newAmountIdr) <= $this->credit_limit_idr;
    }
}
