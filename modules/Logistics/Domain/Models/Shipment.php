<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Logistics\Domain\Enums\Incoterm;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\InvalidShipmentTransitionException;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\ValueObjects\Money;

class Shipment extends LogisticsEntity implements Payable
{
    protected $table = 'lgx_shipments';

    protected $fillable = [
        'tracking_number',
        'shipper_id',
        'consignee_name',
        'consignee_phone',
        'consignee_address',
        'origin_location_id',
        'destination_location_id',
        'service_level',
        'mode',
        'incoterm',
        'declared_value_idr',
        'insured',
        'cod_amount_idr',
        'payment_terms',
        'status',
        'total_chargeable_weight_g',
        'total_amount_idr',
        'cancellation_fee_idr',
        'quote_id',
        'driver_id',
        'invoice_id',
        'booked_at',
        'picked_up_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'consignee_address' => 'array',
        'service_level' => ServiceLevel::class,
        'mode' => TransportMode::class,
        'incoterm' => Incoterm::class,
        'payment_terms' => PaymentTerms::class,
        'status' => ShipmentStatus::class,
        'declared_value_idr' => 'integer',
        'insured' => 'boolean',
        'cod_amount_idr' => 'integer',
        'total_chargeable_weight_g' => 'integer',
        'total_amount_idr' => 'integer',
        'cancellation_fee_idr' => 'integer',
        'quote_id' => 'integer',
        'driver_id' => 'integer',
        'invoice_id' => 'integer',
        'booked_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'shipment_id');
    }

    /**
     * Transition the shipment status safely using state machine rules.
     */
    public function transitionTo(ShipmentStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw InvalidShipmentTransitionException::fromStatus($this->status, $target);
        }

        $this->status = $target;

        if ($target === ShipmentStatus::Booked && ! $this->booked_at) {
            $this->booked_at = now();
        } elseif ($target === ShipmentStatus::PickedUp && ! $this->picked_up_at) {
            $this->picked_up_at = now();
        } elseif ($target === ShipmentStatus::Delivered && ! $this->delivered_at) {
            $this->delivered_at = now();
        } elseif ($target === ShipmentStatus::Cancelled && ! $this->cancelled_at) {
            $this->cancelled_at = now();
        }

        $this->save();
    }

    // --- Payable Implementation ---

    public function payableAmount(): Money
    {
        return Money::IDR($this->total_amount_idr);
    }

    public function payableDescription(): string
    {
        return "Pembayaran ongkos kirim pengiriman {$this->tracking_number}";
    }

    public function payerId(): int
    {
        return $this->shipper_id;
    }

    public function revenueSplits(): array
    {
        return [
            'lgx:unearned_freight' => $this->payableAmount(),
        ];
    }

    public function onPaymentCaptured(PaymentIntent $intent): void
    {
        if ($this->status === ShipmentStatus::Draft) {
            $this->status = ShipmentStatus::Booked;
            $this->booked_at = now();
            $this->save();
        }
    }

    public function onPaymentRefunded(PaymentIntent $intent): void
    {
        if ($this->status->canTransitionTo(ShipmentStatus::Cancelled)) {
            $this->status = ShipmentStatus::Cancelled;
            $this->cancelled_at = now();
            $this->save();
        }
    }
}
