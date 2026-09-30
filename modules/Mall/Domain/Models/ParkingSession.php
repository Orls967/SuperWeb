<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mall\Domain\Enums\ParkingPaymentMethod;
use Modules\Mall\Domain\Enums\ParkingPaymentStatus;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Enums\VehicleType;
use Modules\Mall\Domain\Exceptions\InvalidParkingTicketException;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\Traits\HasUuid;
use Modules\Shared\Domain\ValueObjects\Money;

class ParkingSession extends Model implements Payable
{
    use HasUuid;

    protected $table = 'mall_parking_sessions';

    protected $fillable = [
        'uuid',
        'ticket_number',
        'property_id',
        'parking_zone_id',
        'member_id',
        'vehicle_id',
        'plate_number',
        'vehicle_type',
        'entry_gate',
        'exit_gate',
        'entry_time',
        'exit_time',
        'duration_minutes',
        'base_fee',
        'penalty_fee',
        'discount_amount',
        'validated_by_tenant_id',
        'validation_reference',
        'validation_free_hours',
        'validation_spend_amount',
        'validation_invoice_id',
        'total_fee',
        'payment_method',
        'payment_status',
        'paid_by_user_id',
        'paid_at',
        'is_lost_ticket',
        'status',
    ];

    protected $casts = [
        'vehicle_type' => VehicleType::class,
        'entry_time' => 'datetime',
        'exit_time' => 'datetime',
        'paid_at' => 'datetime',
        'duration_minutes' => 'integer',
        'base_fee' => 'integer',
        'penalty_fee' => 'integer',
        'discount_amount' => 'integer',
        'validation_free_hours' => 'integer',
        'validation_spend_amount' => 'integer',
        'total_fee' => 'integer',
        'payment_method' => ParkingPaymentMethod::class,
        'payment_status' => ParkingPaymentStatus::class,
        'is_lost_ticket' => 'boolean',
        'status' => ParkingSessionStatus::class,
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ParkingZone::class, 'parking_zone_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(ParkingMember::class, 'member_id');
    }

    public function validatedByTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'validated_by_tenant_id');
    }

    public function validationInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'validation_invoice_id');
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === ParkingSessionStatus::ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === ParkingSessionStatus::COMPLETED;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === ParkingPaymentStatus::PAID;
    }

    public function calculateDurationMinutes(?Carbon $atTime = null): int
    {
        $end = $this->exit_time ?? ($atTime ?? Carbon::now());

        return max(1, (int) $this->entry_time->diffInMinutes($end));
    }

    /**
     * Sesi yang potongan validasinya belum ditagihkan ke tenant.
     */
    public function scopeUnbilledValidation(Builder $query, int $tenantId, string $periodMonth): Builder
    {
        return $query->where('validated_by_tenant_id', $tenantId)
            ->whereNull('validation_invoice_id')
            ->where('discount_amount', '>', 0)
            ->whereNotNull('exit_time')
            ->where('exit_time', '<', Carbon::createFromFormat('Y-m', $periodMonth)->endOfMonth());
    }

    // --- Payable Contract Implementation ---

    public function payableAmount(): Money
    {
        return Money::idr($this->total_fee);
    }

    public function payableDescription(): string
    {
        return "Tarif Parkir Duta Mall {$this->ticket_number} ({$this->plate_number})";
    }

    public function payerId(): int
    {
        $payerId = $this->paid_by_user_id ?? $this->member?->user_id;

        if ($payerId === null) {
            throw new InvalidParkingTicketException(
                "Tiket parkir {$this->ticket_number} belum memiliki pembayar terdaftar, pembayaran dompet tidak dapat diproses."
            );
        }

        return (int) $payerId;
    }

    public function revenueSplits(): array
    {
        return [
            'revenue:mall:parking:IDR' => Money::idr($this->total_fee),
        ];
    }

    public function onPaymentCaptured(PaymentIntent $intent): void
    {
        $this->update([
            'payment_status' => ParkingPaymentStatus::PAID,
            'paid_at' => now(),
            'payment_method' => ParkingPaymentMethod::WALLET,
        ]);
    }

    public function onPaymentRefunded(PaymentIntent $intent): void
    {
        $this->update([
            'payment_status' => ParkingPaymentStatus::UNPAID,
            'paid_at' => null,
        ]);
    }
}
