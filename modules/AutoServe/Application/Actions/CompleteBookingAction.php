<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use Exception;
use Illuminate\Support\Str;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Events\BookingCompleted;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Shared\Application\BaseAction;
use Modules\Store\Domain\Models\Product;

class CompleteBookingAction extends BaseAction
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function execute(Booking $booking, ?string $notes = null, ?int $odometerKm = null, ?int $actorId = null): Booking
    {
        return $this->handle($booking, $notes, $odometerKm, $actorId);
    }

    public function handle(Booking $booking, ?string $notes = null, ?int $odometerKm = null, ?int $actorId = null): Booking
    {
        return $this->transaction(function () use ($booking, $notes, $odometerKm, $actorId) {
            foreach ($booking->spareparts as $sparepart) {
                $qty = (int) $sparepart->pivot->quantity;

                // Ensure product exists for this sparepart
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

                try {
                    $this->inventoryService->adjust(
                        $product->id,
                        -$qty,
                        StockMovementReason::SERVICE_USAGE,
                        Booking::class,
                        $booking->id,
                        "Pemakaian bengkel untuk booking {$booking->booking_code}"
                    );
                    $sparepart->update(['stock' => $product->fresh()->cached_stock]);
                } catch (InsufficientStockException $e) {
                    throw new Exception(
                        "Stok {$sparepart->name} tidak mencukupi! Tersisa: {$sparepart->stock}, dibutuhkan: {$qty}"
                    );
                }
            }

            // Odometer validation
            $vehicle = $booking->vehicle;
            if ($vehicle && $odometerKm !== null) {
                if ($odometerKm < (int) $vehicle->odometer_km) {
                    throw new \InvalidArgumentException(
                        "Angka odometer ({$odometerKm} km) tidak boleh lebih rendah dari odometer tercatat sebelumnya ({$vehicle->odometer_km} km)."
                    );
                }
                $vehicle->update(['odometer_km' => $odometerKm]);
            }

            $booking->refresh();
            $sparepartCost = (float) $booking->spareparts->sum('pivot.subtotal');
            $serviceCost = (float) ($booking->service->price ?? 0);

            $booking->update([
                'status' => BookingStatus::Completed,
                'mechanic_notes' => $notes ?? $booking->mechanic_notes,
                'service_cost' => $serviceCost,
                'sparepart_cost' => $sparepartCost,
                'grand_total' => $serviceCost + $sparepartCost,
            ]);

            event(new BookingCompleted(
                booking: $booking,
                odometerKm: $odometerKm ?? $vehicle?->odometer_km,
                actorId: $actorId ?? $booking->mechanic_id
            ));

            return $booking;
        });
    }
}
