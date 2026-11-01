<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Ret\Domain\Exceptions\InvalidFulfillmentStatusTransitionException;
use Modules\Shared\Domain\Traits\HasUuid;

class RetFulfillmentSplit extends Model
{
    use HasUuid;

    protected $table = 'ret_fulfillment_splits';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'split_code',
        'order_id',
        'sku',
        'quantity',
        'allocated_quantity',
        'fulfillment_location_type',
        'facility_id',
        'source_location_id',
        'source_type',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'allocated_quantity' => 'integer',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    protected static array $allowedTransitions = [
        'PLANNED' => ['PICKING', 'CANCELLED'],
        'PICKING' => ['SHIPPED', 'CANCELLED'],
        'SHIPPED' => ['DELIVERED'],
        'DELIVERED' => [],
        'CANCELLED' => [],
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(RetOrder::class, 'order_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->split_code)) {
                $model->split_code = 'SPL-'.strtoupper(Str::random(10));
            }
            if (empty($model->sku)) {
                $model->sku = 'DEFAULT-SKU';
            }
            if (empty($model->quantity)) {
                $model->quantity = $model->allocated_quantity ?: 1;
            }
            if (empty($model->fulfillment_location_type)) {
                $model->fulfillment_location_type = 'DARK_STORE';
            }
            if (empty($model->facility_id)) {
                $model->facility_id = $model->source_location_id ?: 'FAC-DEFAULT';
            }
        });
    }

    public function transitionTo(string $targetStatus): void
    {
        $current = $this->status;
        $allowed = self::$allowedTransitions[$current] ?? [];

        if (! in_array($targetStatus, $allowed, true)) {
            throw new InvalidFulfillmentStatusTransitionException($current, $targetStatus);
        }

        $this->update(['status' => $targetStatus]);
    }
}
