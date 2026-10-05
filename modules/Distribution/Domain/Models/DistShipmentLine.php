<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris pengiriman terhadap baris order. */
class DistShipmentLine extends Model
{
    protected $table = 'dist_shipment_lines';

    protected $fillable = ['shipment_id', 'order_line_id', 'qty'];

    protected $casts = ['order_line_id' => 'integer', 'qty' => 'decimal:6'];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(DistShipment::class, 'shipment_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(DistOrderLine::class, 'order_line_id');
    }
}
