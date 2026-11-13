<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reservasi stok gudang dengan TTL deterministik (X41-2).
 */
class StockReservation extends Model
{
    protected $table = 'wms_stock_reservations';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_COMMITTED = 'committed';

    public const string STATUS_RELEASED = 'released';

    public const string STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'bin_id',
        'lot_number',
        'qty_base',
        'reference_type',
        'reference_id',
        'status',
        'expires_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'qty_base' => 'integer',
        'expires_at' => 'datetime',
        'created_by_user_id' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }
}
