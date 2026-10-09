<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Replenishment pick-face: min/max di bin order-pick (41.6). */
class Replenishment extends Model
{
    protected $table = 'wms_replenishments';

    protected $fillable = ['bin_id', 'product_id', 'min_qty', 'max_qty', 'current_qty', 'suggested_qty', 'status'];

    protected $casts = [
        'product_id' => 'integer', 'min_qty' => 'decimal:6', 'max_qty' => 'decimal:6',
        'current_qty' => 'decimal:6', 'suggested_qty' => 'decimal:6',
    ];

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }

    public function isBelowMin(): bool
    {
        return (float) $this->current_qty < (float) $this->min_qty;
    }

    /** Saran isi ulang agar kembali ke max (41.6). */
    public function calculateSuggested(): float
    {
        if (! $this->isBelowMin()) {
            return 0.0;
        }

        return max(0.0, (float) $this->max_qty - (float) $this->current_qty);
    }
}
