<?php

declare(strict_types=1);

namespace Modules\Store\Application\Actions;

use App\Models\User;
use Exception;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Shared\Application\BaseAction;
use Modules\Store\Domain\Models\Product;

/**
 * Pemilik kendaraan membuat listing mobil bekas (C2C) di Store.
 */
class ListVehicleForSaleAction extends BaseAction
{
    public function execute(
        User $seller,
        Vehicle $vehicle,
        int $price,
        ?string $description = null,
    ): Product {
        if ((int) $vehicle->user_id !== (int) $seller->id) {
            throw new Exception('Kamu hanya dapat menjual kendaraan milikmu sendiri.');
        }

        if (! $vehicle->isActive()) {
            throw new Exception('Kendaraan ini tidak berstatus aktif sehingga tidak dapat dijual.');
        }

        if ($price < 1_000_000) {
            throw new Exception('Harga jual minimal Rp 1.000.000.');
        }

        return $this->transaction(function () use ($seller, $vehicle, $price, $description) {
            $existing = Product::where('productable_type', 'core_vehicle')
                ->where('productable_id', $vehicle->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->is_listed) {
                throw new Exception('Kendaraan ini sudah terpasang di Store dan menunggu pembeli.');
            }

            $vehicle->loadMissing('car.brand');
            $car = $vehicle->car;
            $name = trim(($car?->brand?->name ?? 'Mobil').' '.($car?->model ?? 'Bekas'));
            $label = $vehicle->plate_number ? "{$name} ({$vehicle->plate_number})" : $name;

            $product = $existing ?? new Product;

            $product->fill([
                'uuid' => $product->uuid ?: (string) Str::uuid(),
                'productable_type' => 'core_vehicle',
                'productable_id' => $vehicle->id,
                'seller_id' => $seller->id,
                'sku' => $product->sku ?: ('C2C-'.str_pad((string) $vehicle->id, 6, '0', STR_PAD_LEFT)),
                'name' => $label,
                'slug' => $product->slug ?: Str::slug($label.'-'.Str::random(5)),
                'description' => $description ?? sprintf(
                    'Mobil bekas terawat dari tangan pengguna. Odometer %s km, warna %s. Riwayat servis dapat diverifikasi lewat Paspor Digital kendaraan.',
                    number_format((int) $vehicle->odometer_km, 0, ',', '.'),
                    $vehicle->color ?: 'standar'
                ),
                'price' => $price,
                'compare_at_price' => null,
                'is_listed' => true,
                'is_car' => true,
                'weight_gram' => 1_500_000,
                'images' => $car?->image_url ? [$car->image_url] : null,
            ]);

            $product->save();

            // Stok C2C selalu tepat 1 unit: naikkan kembali ke 1 jika listing lama tersisa 0
            $delta = 1 - (int) $product->cached_stock;
            if ($delta !== 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'qty' => $delta,
                    'reason' => StockMovementReason::INITIAL,
                    'source_type' => 'core_vehicle',
                    'source_id' => $vehicle->id,
                    'note' => "Listing C2C kendaraan {$label}",
                    'created_by' => $seller->id,
                    'created_at' => now(),
                ]);

                $product->cached_stock = 1;
                $product->save();
            }

            return $product->fresh();
        });
    }

    /**
     * Tarik listing C2C dari Store (hanya jika belum ada pembeli yang menahan dana).
     */
    public function unlist(User $seller, Product $product): Product
    {
        if (! $product->isC2c() || (int) $product->seller_id !== (int) $seller->id) {
            throw new Exception('Listing ini bukan milikmu.');
        }

        if ((int) $product->cached_stock < 1) {
            throw new Exception('Listing sedang dalam proses transaksi dan tidak dapat ditarik.');
        }

        $product->update(['is_listed' => false]);

        return $product->fresh();
    }
}
