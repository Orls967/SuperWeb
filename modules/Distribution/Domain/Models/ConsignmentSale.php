<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penjualan konsinyasi yang dilaporkan → memicu faktur (43.4). */
class ConsignmentSale extends Model
{
    protected $table = 'dist_consignment_sales';

    protected $fillable = [
        'consignment_id', 'sold_at', 'qty', 'unit_price_idr', 'status', 'invoice_id', 'reported_by_user_id',
    ];

    protected $casts = [
        'sold_at' => 'date', 'qty' => 'decimal:6', 'unit_price_idr' => 'integer',
        'invoice_id' => 'string', 'reported_by_user_id' => 'integer',
    ];

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(ConsignmentStock::class, 'consignment_id');
    }
}
