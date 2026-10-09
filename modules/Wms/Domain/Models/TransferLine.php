<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris transfer: product + lot + qty. */
class TransferLine extends Model
{
    protected $table = 'wms_transfer_lines';

    protected $fillable = ['transfer_id', 'product_id', 'lot_number', 'qty', 'status'];

    protected $casts = ['product_id' => 'integer', 'qty' => 'decimal:6'];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class, 'transfer_id');
    }
}
