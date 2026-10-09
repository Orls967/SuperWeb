<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stok per bin + lot/serial + status (41.2). */
class BinStock extends Model
{
    protected $table = 'wms_bin_stocks';

    protected $fillable = ['bin_id', 'product_id', 'lot_number', 'serial_number', 'status', 'qty'];

    protected $casts = ['product_id' => 'integer', 'qty' => 'decimal:6'];

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }
}
