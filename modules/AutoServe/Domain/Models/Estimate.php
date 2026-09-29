<?php

declare(strict_types=1);

namespace Modules\AutoServe\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Enums\EstimateStatus;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\Exceptions\InvalidStateTransition;
use Modules\Shared\Domain\Traits\HasUuid;
use Modules\Shared\Domain\ValueObjects\Money;

/**
 * Estimasi biaya perbaikan yang dibuat mekanik dan disetujui customer.
 * Menjadi Payable agar dananya bisa ditahan (hold) di escrow sampai servis selesai.
 */
class Estimate extends Model implements Payable
{
    use HasFactory, HasUuid;

    protected $table = 'serve_estimates';

    protected $fillable = [
        'uuid',
        'booking_id',
        'created_by',
        'items',
        'service_total',
        'parts_total',
        'total',
        'final_service_total',
        'final_parts_total',
        'status',
        'sent_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'extra_amount',
        'extra_charge_intent_id',
        'payment_intent_id',
        'backorder_order_id',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'service_total' => 'integer',
            'parts_total' => 'integer',
            'total' => 'integer',
            'final_service_total' => 'integer',
            'final_parts_total' => 'integer',
            'extra_amount' => 'integer',
            'status' => EstimateStatus::class,
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }

    public function paymentIntents(): MorphMany
    {
        return $this->morphMany(PaymentIntent::class, 'payable');
    }

    public function transitionTo(EstimateStatus $next): self
    {
        $current = $this->status;

        if (! $current->canTransitionTo($next)) {
            throw InvalidStateTransition::fromTo($current, $next, 'Estimate');
        }

        $this->status = $next;
        $this->save();

        return $this;
    }

    public function isApproved(): bool
    {
        return $this->status === EstimateStatus::Approved;
    }

    /**
     * Item jasa saja.
     *
     * @return array<int, array<string, mixed>>
     */
    public function serviceItems(): array
    {
        return array_values(array_filter($this->items ?? [], fn (array $item) => ($item['type'] ?? '') === 'service'));
    }

    /**
     * Item sparepart saja.
     *
     * @return array<int, array<string, mixed>>
     */
    public function partItems(): array
    {
        return array_values(array_filter($this->items ?? [], fn (array $item) => ($item['type'] ?? '') === 'part'));
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp '.number_format($this->total, 0, ',', '.');
    }

    /* -----------------------------------------------------------------
     | Payable Contract Implementation
     | ----------------------------------------------------------------- */

    /**
     * Nominal posting berikutnya: selalu sama dengan jumlah revenueSplits(),
     * yaitu nilai final bila sudah ditetapkan, atau total estimasi bila belum.
     */
    public function payableAmount(): Money
    {
        if ($this->final_service_total !== null || $this->final_parts_total !== null) {
            return Money::fromIdr((int) $this->final_service_total + (int) $this->final_parts_total);
        }

        return Money::fromIdr($this->total);
    }

    public function payableDescription(): string
    {
        $this->loadMissing('booking');

        return "Estimasi Perbaikan {$this->booking?->booking_code}";
    }

    public function payerId(): int
    {
        $this->loadMissing('booking');

        return (int) $this->booking->customer_id;
    }

    /**
     * Split pendapatan memakai nilai final bila sudah ditetapkan (saat capture atau
     * tagihan selisih), jika belum memakai nilai estimasi.
     */
    public function revenueSplits(): array
    {
        $service = $this->final_service_total ?? $this->service_total;
        $parts = $this->final_parts_total ?? $this->parts_total;

        $splits = [];

        if ($service > 0) {
            $splits['revenue:autoserve:service:IDR'] = Money::fromIdr($service);
        }

        if ($parts > 0) {
            $splits['revenue:autoserve:parts:IDR'] = Money::fromIdr($parts);
        }

        return $splits;
    }

    public function onPaymentCaptured(PaymentIntent $intent): void
    {
        $this->loadMissing('booking');
        $booking = $this->booking;

        if ($booking === null) {
            return;
        }

        $booking->payment_status = 'paid';
        $booking->paid_at = now();

        if ($booking->status === BookingStatus::Completed->value) {
            $booking->status = BookingStatus::Invoiced->value;
        }

        $booking->save();
    }

    public function onPaymentRefunded(PaymentIntent $intent): void
    {
        $this->loadMissing('booking');

        $this->booking?->update(['payment_status' => 'refunded']);
    }
}
