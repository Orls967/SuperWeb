<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shared\Domain\Traits\HasUuid;

class RetOrder extends Model
{
    use HasUuid;

    protected $table = 'ret_orders';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'order_number',
        'customer_id',
        'channel_id',
        'seller_id',
        'total_amount_minor',
        'commission_amount_minor',
        'packaging_deposit_minor',
        'coupon_code',
        'status',
        'idempotency_key',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'total_amount_minor' => 'integer',
        'commission_amount_minor' => 'integer',
        'packaging_deposit_minor' => 'integer',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $model): void {
            if (in_array($model->status, ['PAID', 'DELIVERED', 'FULFILLED', 'SETTLED'])) {
                throw new DomainException('Paid or fulfilled orders are append-only facts and cannot be deleted.');
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(RetOrderItem::class, 'order_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(RetChannel::class, 'channel_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(RetSeller::class, 'seller_id');
    }

    public function fulfillmentSplits(): HasMany
    {
        return $this->hasMany(RetFulfillmentSplit::class, 'order_id');
    }

    public function packagingDeposits(): HasMany
    {
        return $this->hasMany(RetPackagingDeposit::class, 'order_id');
    }

    public function cancelOrder(string $reason): void
    {
        if ($this->status === 'DELIVERED' || $this->status === 'FULFILLED') {
            throw new DomainException("Cannot cancel an already completed order [{$this->order_number}].");
        }

        $this->update([
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
