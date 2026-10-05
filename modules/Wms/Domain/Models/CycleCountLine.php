<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris hitung fisik per bin/product/lot. */
class CycleCountLine extends Model
{
    protected $table = 'wms_cycle_count_lines';

    protected $fillable = [
        'count_id', 'bin_id', 'product_id', 'system_qty', 'counted_qty', 'variance_qty', 'lot_number',
    ];

    protected $casts = [
        'product_id' => 'integer', 'system_qty' => 'decimal:6',
        'counted_qty' => 'decimal:6', 'variance_qty' => 'decimal:6',
    ];

    public function count(): BelongsTo
    {
        return $this->belongsTo(CycleCount::class, 'count_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }
}
