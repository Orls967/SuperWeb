<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AutoServe\Domain\Models\Sparepart;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Store\Domain\Models\Product;

/**
 * Pastikan setiap sparepart bengkel punya produk inventori padanannya,
 * sehingga stok bengkel dan stok toko selalu merujuk angka yang sama.
 */
class ResolveSparepartProductAction
{
    public function execute(Sparepart $sparepart): Product
    {
        try {
            return DB::transaction(function () use ($sparepart) {
                $product = Product::firstOrCreate(
                    [
                        'productable_type' => 'serve_sparepart',
                        'productable_id' => $sparepart->id,
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'sku' => $sparepart->code ?: ('PART-'.$sparepart->id.'-'.Str::random(3)),
                        'name' => $sparepart->name,
                        'slug' => Str::slug($sparepart->name.'-'.$sparepart->id.'-'.Str::random(3)),
                        'price' => $sparepart->price,
                        'cached_stock' => (int) $sparepart->stock,
                        'is_listed' => false,
                        'is_car' => false,
                        'weight_gram' => 500,
                    ]
                );

                if ($product->wasRecentlyCreated && (int) $sparepart->stock > 0) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'qty' => (int) $sparepart->stock,
                        'reason' => StockMovementReason::INITIAL,
                        'source_type' => 'serve_sparepart',
                        'source_id' => $sparepart->id,
                        'note' => 'Stok awal sparepart bengkel',
                        'created_at' => now(),
                    ]);
                }

                return $product;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Product::query()
                ->where('productable_type', 'serve_sparepart')
                ->where('productable_id', $sparepart->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }
}
