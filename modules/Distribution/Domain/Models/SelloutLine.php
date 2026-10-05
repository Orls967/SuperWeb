<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris sell-out per SKU. */
class SelloutLine extends Model
{
    protected $table = 'dist_sellout_lines';

    protected $fillable = ['report_id', 'product_id', 'sku', 'qty', 'unit_price_idr'];

    protected $casts = ['product_id' => 'integer', 'qty' => 'decimal:6', 'unit_price_idr' => 'integer'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(SelloutReport::class, 'report_id');
    }
}
