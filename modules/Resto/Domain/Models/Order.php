<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Resto\Domain\Enums\ConsumedState;
use Modules\Resto\Domain\Enums\OrderChannel;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Shared\Domain\ValueObjects\Money;

class Order extends Model implements Payable
{
    protected $table = 'resto_orders';

    protected $fillable = [
        'uuid',
        'outlet_id',
        'number',
        'table_session_id',
        'channel',
        'customer_id',
        'guest_name',
        'subtotal',
        'discount',
        'service_charge',
        'tax_pb1',
        'rounding',
        'grand_total',
        'payment_method',
        'parking_ticket_number',
        'parking_validation_hours',
        'mall_voucher_code',
        'mall_voucher_discount',
        'loyalty_points_earned',
        'status',
        'paid_at',
        'shift_id',
        'idempotency_key',
        'client_created_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'channel' => OrderChannel::class,
        'subtotal' => 'integer',
        'discount' => 'integer',
        'service_charge' => 'integer',
        'tax_pb1' => 'integer',
        'rounding' => 'integer',
        'grand_total' => 'integer',
        'parking_validation_hours' => 'integer',
        'mall_voucher_discount' => 'integer',
        'loyalty_points_earned' => 'integer',
        'paid_at' => 'datetime',
        'client_created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::PAID;
    }

    // --- Payable Contract Implementation ---

    public function payableAmount(): Money
    {
        return Money::fromIdr($this->grand_total);
    }

    public function payableDescription(): string
    {
        return "Pesanan RM Sari Ranah {$this->number}";
    }

    public function payerId(): int
    {
        return (int) ($this->customer_id ?? auth()->id() ?? 1);
    }

    public function revenueSplits(): array
    {
        $outletCode = $this->outlet?->code ?: 'DM-01';
        $splits = [];

        $foodTotal = 0;
        $beverageTotal = 0;

        foreach ($this->items as $item) {
            if ($item->consumed_state === ConsumedState::CONSUMED) {
                $cat = $item->menuItem?->category?->name;
                if ($cat === 'Minuman') {
                    $beverageTotal += $item->line_total;
                } else {
                    $foodTotal += $item->line_total;
                }
            }
        }

        // Apply discount proportionally if any
        $totalFoodAndBev = $foodTotal + $beverageTotal;
        if ($this->discount > 0 && $totalFoodAndBev > 0) {
            $foodRatio = $foodTotal / $totalFoodAndBev;
            $foodDiscount = (int) round($this->discount * $foodRatio);
            $beverageDiscount = $this->discount - $foodDiscount;

            $foodTotal = max(0, $foodTotal - $foodDiscount);
            $beverageTotal = max(0, $beverageTotal - $beverageDiscount);
        }

        if ($foodTotal > 0) {
            $splits["revenue:resto:{$outletCode}:food:IDR"] = Money::fromIdr($foodTotal);
        }
        if ($beverageTotal > 0) {
            $splits["revenue:resto:{$outletCode}:beverage:IDR"] = Money::fromIdr($beverageTotal);
        }
        if ($this->service_charge > 0) {
            $splits["revenue:resto:{$outletCode}:service:IDR"] = Money::fromIdr($this->service_charge);
        }
        if ($this->tax_pb1 > 0) {
            $splits["revenue:resto:{$outletCode}:tax_pb1:IDR"] = Money::fromIdr($this->tax_pb1);
        }

        // Balancing adjustment to guarantee sum(splits) == payableAmount()
        $currentSum = 0;
        foreach ($splits as $splitMoney) {
            $currentSum += $splitMoney->amount->toInt();
        }

        $diff = $this->grand_total - $currentSum;
        if ($diff !== 0) {
            $firstKey = ! empty($splits) ? array_key_first($splits) : "revenue:resto:{$outletCode}:food:IDR";
            $existingVal = isset($splits[$firstKey]) ? $splits[$firstKey]->amount->toInt() : 0;
            $splits[$firstKey] = Money::fromIdr($existingVal + $diff);
        }

        return $splits;
    }

    public function onPaymentCaptured(PaymentIntent $intent): void
    {
        $this->status = OrderStatus::PAID;
        $this->paid_at = now();
        $this->save();
    }

    public function onPaymentRefunded(PaymentIntent $intent): void
    {
        $this->status = OrderStatus::REFUNDED;
        $this->save();
    }
}
