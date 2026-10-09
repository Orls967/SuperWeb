<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stok kritis distributor → saran replenishment VMI (43.8). */
class StockLevel extends Model
{
    protected $table = 'dist_stock_levels';

    protected $fillable = [
        'distributor_id', 'product_id', 'sku', 'qty_on_hand', 'min_qty', 'max_qty',
        'avg_daily_sales', 'suggested_order_qty', 'status',
    ];

    protected $casts = [
        'product_id' => 'integer', 'qty_on_hand' => 'decimal:6', 'min_qty' => 'decimal:6',
        'max_qty' => 'decimal:6', 'avg_daily_sales' => 'decimal:6', 'suggested_order_qty' => 'decimal:6',
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function isCritical(): bool
    {
        return (float) $this->qty_on_hand <= (float) $this->min_qty;
    }

    /** Saran order: isi sampai max, dibulatkan ke atas bilangan bulat. */
    public function calculateSuggestion(): float
    {
        if (! $this->isCritical()) {
            return 0.0;
        }

        $target = max((float) $this->max_qty, (float) $this->min_qty + 1);
        $gap = $target - (float) $this->qty_on_hand;
        // Tambah buffer hari stok kritis (7 hari penjualan rata-rata).
        $buffer = 7 * (float) $this->avg_daily_sales;

        return (float) ceil($gap + $buffer);
    }
}
