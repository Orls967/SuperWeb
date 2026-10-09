<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Order distributor: validasi limit + ATP, alokasi, backorder (43.1). */
class DistOrder extends Model
{
    use HasUuids;

    protected $table = 'dist_orders';

    protected $fillable = [
        'number', 'distributor_id', 'status', 'subtotal_idr', 'discount_idr', 'ppn_idr',
        'total_idr', 'currency', 'shipping_address', 'requested_date',
        'allocation_strategy', 'notes', 'created_by_user_id',
        'allocated_at', 'shipped_at', 'delivered_at',
    ];

    protected $casts = [
        'subtotal_idr' => 'integer', 'discount_idr' => 'integer', 'ppn_idr' => 'integer',
        'total_idr' => 'integer', 'requested_date' => 'date', 'created_by_user_id' => 'integer',
        'allocated_at' => 'datetime', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime',
    ];

    public const STATUSES = ['draft', 'allocated', 'partial', 'shipped', 'delivered', 'cancelled', 'backordered'];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DistOrderLine::class, 'order_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(DistShipment::class, 'order_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(DistInvoice::class, 'order_id');
    }

    public function canTransitionTo(string $to): bool
    {
        $allowed = [
            'draft' => ['allocated', 'cancelled'],
            'allocated' => ['partial', 'shipped', 'cancelled'],
            'partial' => ['shipped', 'cancelled'],
            'shipped' => ['delivered', 'cancelled'],
            'delivered' => [], 'backordered' => ['allocated', 'cancelled'],
            'cancelled' => [],
        ];

        return in_array($to, $allowed[$this->status] ?? [], true);
    }
}
