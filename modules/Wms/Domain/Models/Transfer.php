<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Transfer antar-gudang + cross-dock (41.4). */
class Transfer extends Model
{
    use HasUuids;

    protected $table = 'wms_transfers';

    protected $fillable = [
        'number', 'from_warehouse_id', 'to_warehouse_id', 'status', 'cross_dock',
        'tracking_number', 'appointment_id', 'notes', 'created_by_user_id',
    ];

    protected $casts = [
        'cross_dock' => 'boolean', 'appointment_id' => 'integer', 'created_by_user_id' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(TransferLine::class, 'transfer_id');
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function canTransitionTo(string $to): bool
    {
        $allowed = [
            'draft' => ['in_transit', 'cancelled'],
            'in_transit' => ['received', 'cancelled'],
            'received' => [], 'cancelled' => [],
        ];

        return in_array($to, $allowed[$this->status] ?? [], true);
    }
}
