<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\ValueObjects\Money;

class LogisticsInvoice extends LogisticsEntity implements Payable
{
    protected $table = 'lgx_invoices';

    protected $fillable = [
        'invoice_number',
        'shipper_id',
        'billing_period',
        'total_amount_idr',
        'paid_amount_idr',
        'status',
        'due_date',
        'paid_at',
    ];

    protected $casts = [
        'shipper_id' => 'integer',
        'total_amount_idr' => 'integer',
        'paid_amount_idr' => 'integer',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'invoice_id');
    }

    public function remainingAmount(): int
    {
        return max(0, $this->total_amount_idr - $this->paid_amount_idr);
    }

    // --- Payable Implementation ---

    public function payableAmount(): Money
    {
        return Money::IDR($this->remainingAmount());
    }

    public function payableDescription(): string
    {
        return "Pembayaran tagihan invoice logistik {$this->invoice_number}";
    }

    public function payerId(): int
    {
        return $this->shipper_id;
    }

    public function revenueSplits(): array
    {
        // Credit the AR account to clear receivable
        return [
            "lgx:ar:{$this->shipper_id}" => $this->payableAmount(),
        ];
    }

    public function onPaymentCaptured(PaymentIntent $intent): void
    {
        $this->status = 'paid';
        $this->paid_amount_idr = $this->total_amount_idr;
        $this->paid_at = now();
        $this->save();
    }

    public function onPaymentRefunded(PaymentIntent $intent): void
    {
        $this->status = 'unpaid';
        $this->paid_amount_idr = 0;
        $this->paid_at = null;
        $this->save();
    }
}
