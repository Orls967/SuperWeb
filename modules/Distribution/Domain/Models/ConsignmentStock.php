<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Stok konsinyasi: milik prinsipal, di lokasi distributor (43.4). */
class ConsignmentStock extends Model
{
    use HasUuids;

    protected $table = 'dist_consignment_stocks';

    protected $fillable = [
        'distributor_id', 'product_id', 'sku', 'qty', 'qty_sold_unbilled', 'value_idr', 'last_reconciled_at',
    ];

    protected $casts = [
        'product_id' => 'integer', 'qty' => 'decimal:6', 'qty_sold_unbilled' => 'decimal:6',
        'value_idr' => 'integer', 'last_reconciled_at' => 'date',
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(ConsignmentSale::class, 'consignment_id');
    }
}
