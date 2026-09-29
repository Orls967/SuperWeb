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
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\ValueObjects\Money;
use Modules\Store\Domain\Enums\OrderStatus;

class Order extends Model implements Payable
{
    use HasFactory;

    protected $table = 'store_orders';

    protected $fillable = [
        'uuid',
        'number',
        'user_id',
        'status',
        'subtotal',
        'shipping_fee',
        'discount',
        'grand_total',
        'shipping_address',
        'tracking_number',
        'paid_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'subtotal' => 'integer',
        'shipping_fee' => 'integer',
        'discount' => 'integer',
        'grand_total' => 'integer',
        'shipping_address' => 'array',
        'paid_at' => 'datetime',
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

        $hasOnlyCars = $this->items->every(fn (OrderItem $item) => $item->product?->is_car);

        // If only cars, complete immediately; otherwise set to PAID (or PROCESSING)
        $this->status = $hasOnlyCars ? OrderStatus::COMPLETED : OrderStatus::PAID;
        $this->paid_at = now();
        $this->save();

        if ($hasOnlyCars || $this->status === OrderStatus::COMPLETED) {
            $this->fulfillCarPurchases();
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
    public function fulfillCarPurchases(): void
    {
        $acquirer = app(AcquiresVehicle::class);

        foreach ($this->items as $item) {
            $product = $item->product;
            if ($product && $product->is_car && $product->productable_id) {
                // If productable_type is dex_car or Car model
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
