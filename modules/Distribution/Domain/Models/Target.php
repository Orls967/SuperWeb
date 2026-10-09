<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Target penjualan per SKU per periode (sell-in / sell-out). */
class Target extends Model
{
    protected $table = 'dist_targets';

    protected $fillable = [
        'distributor_id', 'product_sku', 'period', 'target_qty', 'achieved_qty', 'basis',
    ];

    protected $casts = ['target_qty' => 'decimal:6', 'achieved_qty' => 'decimal:6'];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function achievementPercent(): float
    {
        $target = (float) $this->target_qty;
        if ($target <= 0) {
            return 0.0;
        }

        return round(((float) $this->achieved_qty / $target) * 100, 2);
    }
}
