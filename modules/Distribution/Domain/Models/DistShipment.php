<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pengiriman order distributor + POD (43.2). */
class DistShipment extends Model
{
    use HasUuids;

    protected $table = 'dist_shipments';

    protected $fillable = [
        'number', 'order_id', 'tracking_number', 'mode', 'status',
        'picked_at', 'shipped_at', 'pod_at', 'pod_name', 'pod_note', 'notes', 'created_by_user_id',
    ];

    protected $casts = [
        'picked_at' => 'datetime', 'shipped_at' => 'datetime', 'pod_at' => 'datetime',
        'created_by_user_id' => 'integer',
    ];

    public const MODES = ['ftl', 'ltl', 'multimoda', 'courier'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(DistOrder::class, 'order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DistShipmentLine::class, 'shipment_id');
    }
}
