<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\Traits\HasUuid;
use Modules\Shared\Domain\ValueObjects\Money;

class Invoice extends Model implements Payable
{
    use HasUuid;

    protected $table = 'mall_invoices';

    protected $fillable = [
        'uuid',
        'invoice_number',
        'lease_id',
        'tenant_id',
        'property_id',
        'period_month',
        'subtotal',
        'penalty_amount',
        'total_amount',
        'paid_amount',
        'status',
        'due_date',
        'issued_at',
        'paid_at',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'penalty_amount' => 'integer',
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'status' => InvoiceStatus::class,
        'due_date' => 'date',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id');
    }

    public function remainingAmount(): int
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::PAID;
    }

    public function isOverdue(): bool
    {
        if ($this->isPaid() || $this->status === InvoiceStatus::CANCELLED) {
            return false;
        }

        return Carbon::now()->startOfDay()->greaterThan(Carbon::parse($this->due_date)->startOfDay());
    }

    public function daysOverdue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return abs((int) Carbon::now()->startOfDay()->diffInDays(Carbon::parse($this->due_date)->startOfDay()));
    }

    // --- Payable Contract Implementation ---

    public function payableAmount(): Money
    {
        return Money::idr($this->remainingAmount());
    }

    public function payableDescription(): string
    {
        return "Pembayaran Tagihan Mall {$this->invoice_number} ({$this->period_month}) - {$this->tenant?->brand_name}";
    }

    public function payerId(): int
    {
        return (int) ($this->tenant?->user_id ?? 1);
    }

    public function revenueSplits(): array
    {
        $splits = [];
        foreach ($this->lines as $line) {
            $rem = $line->remainingAmount();
            if ($rem > 0) {
                $code = $line->type->ledgerAccountCode();
                $current = $splits[$code] ?? Money::idr(0);
                $splits[$code] = $current->plus(Money::idr($rem));
            }
        }

        return $splits;
    }

    public function onPaymentCaptured(PaymentIntent $intent): void
    {
        $this->update([
            'paid_amount' => $this->total_amount,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'payment_reference' => $intent->uuid,
        ]);

        foreach ($this->lines as $line) {
            $line->update([
                'paid_amount' => $line->amount,
                'status' => 'paid',
            ]);
        }
    }

    public function onPaymentRefunded(PaymentIntent $intent): void
    {
        $this->update([
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'paid_at' => null,
            'payment_reference' => null,
        ]);

        foreach ($this->lines as $line) {
            $line->update([
                'paid_amount' => 0,
                'status' => 'unpaid',
            ]);
        }
    }
}
