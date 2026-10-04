<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Supplier\Contracts\ReferenceCostUpdater;

/**
 * 32.8: harga terakhir pemasok → MAC referensi bahan Resto (kontrak, bukan impor domain).
 *
 * Hanya konversi 1:1 untuk mata uang IDR (konversi multi-currency ditangani
 * Fase 48); selain itu diabaikan dengan false.
 */
class IngredientReferenceCostUpdater implements ReferenceCostUpdater
{
    public function updateReferenceCost(int $internalProductId, string $unitPrice, string $currency): bool
    {
        if (strtoupper($currency) !== 'IDR') {
            return false;
        }

        // Perbarui harga beli terakhir + MAC referensi untuk seluruh outlet
        // yang memuat bahan ini (uniqueness: (ingredient_id, outlet_id)).
        return DB::table('resto_ingredient_costs')
            ->where('ingredient_id', $internalProductId)
            ->update([
                'last_purchase_cost' => $unitPrice,
                'moving_avg_cost_per_base_unit' => $unitPrice,
                'updated_at' => now(),
            ]) > 0;
    }
}
