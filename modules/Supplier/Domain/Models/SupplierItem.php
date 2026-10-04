<?php

declare(strict_types=1);

namespace Modules\Supplier\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierItem extends Model
{
    protected $table = 'sup_items';

    protected $fillable = [
        'supplier_id', 'supplier_sku', 'internal_product_id', 'internal_sku', 'name', 'unit',
        'moq', 'lead_time_days', 'currency', 'is_active',
    ];

    protected $casts = [
        'internal_product_id' => 'integer', 'moq' => 'integer', 'lead_time_days' => 'integer', 'is_active' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(SupplierPriceTier::class, 'item_id')->orderBy('min_qty');
    }

    /**
     * Harga berlaku untuk kuantitas & tanggal pada hari ini (tanpa overlap).
     */
    public function activeTier(int $qty, ?string $asOfDate = null): ?SupplierPriceTier
    {
        $asOf = $asOfDate ?? now()->toDateString();

        return $this->priceTiers()
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $asOf);
            })
            ->where('min_qty', '<=', $qty)
            ->where(function ($q) use ($qty) {
                $q->whereNull('max_qty')->orWhere('max_qty', '>=', $qty);
            })
            ->orderByDesc('min_qty')
            ->first();
    }
}
