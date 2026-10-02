<?php

declare(strict_types=1);

namespace Modules\Store\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Core\Contracts\TransfersVehicleOwnership;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Logistics\Contracts\ShipmentBooking;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\ValueObjects\Money;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Events\OrderPaid;

class Order extends Model implements Payable
{
    use HasFactory;

    protected $table = 'store_orders';

    protected $fillable = [
        'uuid',
        'number',
        'user_id',
        'seller_id',
        'status',
        'subtotal',
        'shipping_fee',
        'discount',
        'grand_total',
        'shipping_address',
        'tracking_number',
        'paid_at',
        'handover_at',
        'received_at',
        'auto_capture_at',
        'disputed_at',
        'dispute_reason',
        'cancelled_at',
        'cancellation_reason',
    ];

    /** Fee platform untuk transaksi C2C (1% dari nilai transaksi). */
    public const C2C_PLATFORM_FEE_PERCENT = 1;

    /** Batas waktu konfirmasi pembeli sebelum dana otomatis dicairkan ke penjual. */
    public const C2C_AUTO_CAPTURE_DAYS = 3;

    protected $casts = [
        'status' => OrderStatus::class,
        'subtotal' => 'integer',
        'shipping_fee' => 'integer',
        'discount' => 'integer',
        'grand_total' => 'integer',
        'shipping_address' => 'array',
        'paid_at' => 'datetime',
        'handover_at' => 'datetime',
        'received_at' => 'datetime',
        'auto_capture_at' => 'datetime',
        'disputed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
            if (empty($order->number)) {
                $order->number = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(5));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Pesanan C2C: pembeli membeli kendaraan milik pengguna lain lewat escrow.
     */
    public function isC2c(): bool
    {
        return $this->seller_id !== null;
    }

    /**
     * Bagian penjual: nilai transaksi dikurangi fee platform.
     */
    public function c2cSellerAmount(): int
    {
        return $this->grand_total - $this->c2cPlatformFee();
    }

    /**
     * Fee platform 1% (dibulatkan ke bawah agar total split tetap presisi).
     */
    public function c2cPlatformFee(): int
    {
        return intdiv($this->grand_total * self::C2C_PLATFORM_FEE_PERCENT, 100);
    }

    public function paymentIntents(): MorphMany
    {
        return $this->morphMany(PaymentIntent::class, 'payable');
    }

    public function scopeForUser(Builder $query, int|User $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->where('user_id', $userId);
    }

    public function getFormattedGrandTotalAttribute(): string
    {
        return 'Rp '.number_format($this->grand_total, 0, ',', '.');
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return 'Rp '.number_format($this->subtotal, 0, ',', '.');
    }

    public function getFormattedShippingFeeAttribute(): string
    {
        return 'Rp '.number_format($this->shipping_fee, 0, ',', '.');
    }

    /* -----------------------------------------------------------------
     | Payable Contract Implementation
     | ----------------------------------------------------------------- */

    public function payableAmount(): Money
    {
        return Money::fromIdr($this->grand_total);
    }

    public function payableDescription(): string
    {
        return "Pembayaran Pesanan {$this->number}";
    }

    public function payerId(): int
    {
        return (int) $this->user_id;
    }

    public function revenueSplits(): array
    {
        if ($this->isC2c()) {
            return [
                "wallet:user:{$this->seller_id}:IDR" => Money::fromIdr($this->c2cSellerAmount()),
                'revenue:store:IDR' => Money::fromIdr($this->c2cPlatformFee()),
            ];
        }

        return [
            'revenue:store:IDR' => Money::fromIdr($this->grand_total),
        ];
    }

    public function onPaymentCaptured(PaymentIntent $intent): void
    {
        $inventoryService = app(InventoryService::class);

        // Commit reservations to confirmed sales
        foreach ($this->items as $item) {
            if ($item->reservation_id) {
                $inventoryService->commit(
                    $item->reservation_id,
                    StockMovementReason::SALE,
                    "Penjualan via Order {$this->number}"
                );
            }
        }

        if ($this->isC2c()) {
            $this->status = OrderStatus::COMPLETED;
            $this->paid_at = $this->paid_at ?? now();
            $this->received_at = $this->received_at ?? now();
            $this->save();

            $this->transferC2cVehicles();

            return;
        }

        $hasOnlyCars = $this->items->every(fn (OrderItem $item) => $item->product?->is_car);

        // If only cars, complete immediately; otherwise set to PAID (or PROCESSING)
        $this->status = $hasOnlyCars ? OrderStatus::COMPLETED : OrderStatus::PAID;
        $this->paid_at = now();
        $this->save();

        if ($hasOnlyCars || $this->status === OrderStatus::COMPLETED) {
            $this->fulfillCarPurchases();
        }

        // Dispatch OrderPaid for non-C2C physical orders to trigger shipment creation
        if (! $hasOnlyCars) {
            event(new OrderPaid(
                $this->id,
                (int) $this->user_id,
                (int) $this->grand_total,
            ));
        }
    }

    public function onPaymentRefunded(PaymentIntent $intent): void
    {
        $inventoryService = app(InventoryService::class);

        // Restore stock
        foreach ($this->items as $item) {
            $inventoryService->adjust(
                $item->product_id,
                $item->qty,
                StockMovementReason::RETURN,
                self::class,
                $this->id,
                "Restorasi stok retur Order {$this->number}",
                auth()->id()
            );
        }

        $this->status = OrderStatus::REFUNDED;
        $this->cancelled_at = now();
        $this->cancellation_reason = 'Pembayaran dibatalkan dan direfund';
        $this->save();

        if (app()->bound(ShipmentBooking::class)) {
            app(ShipmentBooking::class)->cancelForOrder(
                'store_order',
                (int) $this->id,
                $this->cancellation_reason
            );
        }
    }

    /**
     * Mark order as completed and fulfill car acquisitions if any.
     */
    public function markAsCompleted(): void
    {
        $this->status = OrderStatus::COMPLETED;
        $this->save();

        $this->fulfillCarPurchases();
    }

    /**
     * Fulfill car items by adding them to customer garage and removing from wishlist.
     */
    /**
     * Pindahkan kepemilikan kendaraan C2C ke pembeli dan nonaktifkan listing penjual.
     */
    public function transferC2cVehicles(): void
    {
        $transferrer = app(TransfersVehicleOwnership::class);

        foreach ($this->items as $item) {
            $product = $item->product;

            if (! $product || $product->productable_type !== 'core_vehicle' || ! $product->productable_id) {
                continue;
            }

            $transferrer->handle(
                vehicle: (int) $product->productable_id,
                toUserId: (int) $this->user_id,
                viaType: 'store_order',
                viaId: (int) $this->id,
                priceIdr: (int) $item->price_snapshot,
                actorId: (int) $this->user_id,
            );

            $product->update(['is_listed' => false]);
        }
    }

    public function fulfillCarPurchases(): void
    {
        $acquirer = app(AcquiresVehicle::class);

        foreach ($this->items as $item) {
            $product = $item->product;
            if ($product && $product->is_car && $product->productable_type === 'dex_car' && $product->productable_id) {
                for ($i = 0; $i < $item->qty; $i++) {
                    $acquirer->handle(
                        user: $this->user_id,
                        car: (int) $product->productable_id,
                        plateNumber: 'B '.rand(1000, 9999).' '.strtoupper(Str::random(3)),
                        color: 'Hitam',
                        vin: 'VIN'.strtoupper(Str::random(14)),
                        odometerKm: 0,
                        acquiredViaType: 'order',
                        acquiredViaId: $this->id
                    );
                }
            }
        }
    }
}
