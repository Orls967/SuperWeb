<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use Exception;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Events\BookingCompleted;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\ValueObjects\Money;

class CompleteBookingAction extends BaseAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ResolveSparepartProductAction $resolveSparepartProduct,
        private readonly PaymentGateway $paymentGateway,
    ) {}

    public function execute(Booking $booking, ?string $notes = null, ?int $odometerKm = null, ?int $actorId = null): Booking
    {
        return $this->handle($booking, $notes, $odometerKm, $actorId);
    }

    public function handle(Booking $booking, ?string $notes = null, ?int $odometerKm = null, ?int $actorId = null): Booking
    {
        return $this->transaction(function () use ($booking, $notes, $odometerKm, $actorId) {
            $booking->loadMissing(['spareparts', 'service']);

            $serviceCost = (int) ($booking->service->price ?? 0);
            $sparepartCost = (int) $booking->spareparts->sum('pivot.subtotal');
            $finalTotal = $serviceCost + $sparepartCost;

            $estimate = $booking->approvedEstimate();
            $heldIntent = $estimate !== null ? $this->heldIntent($estimate) : null;
            $heldAmount = $heldIntent !== null ? (int) $heldIntent->amount : 0;

            // Biaya aktual melebihi dana yang ditahan: minta persetujuan tambahan dulu
            if ($heldIntent !== null
                && $finalTotal > $heldAmount
                && $estimate->extra_charge_intent_id === null) {
                $estimate->update(['extra_amount' => $finalTotal - $heldAmount]);

                $booking->update([
                    'mechanic_notes' => $notes ?? $booking->mechanic_notes,
                    'service_cost' => $serviceCost,
                    'sparepart_cost' => $sparepartCost,
                    'grand_total' => $finalTotal,
                ]);

                $booking->transitionTo(BookingStatus::AwaitingExtraApproval);

                return $booking->fresh();
            }

            foreach ($booking->spareparts as $sparepart) {
                $qty = (int) $sparepart->pivot->quantity;
                $product = $this->resolveSparepartProduct->execute($sparepart);

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
                } catch (InsufficientStockException) {
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

            $booking->update([
                'status' => BookingStatus::Completed,
                'mechanic_notes' => $notes ?? $booking->mechanic_notes,
                'service_cost' => $serviceCost,
                'sparepart_cost' => $sparepartCost,
                'grand_total' => $finalTotal,
            ]);

            event(new BookingCompleted(
                booking: $booking,
                odometerKm: $odometerKm ?? $vehicle?->odometer_km,
                actorId: $actorId ?? $booking->mechanic_id
            ));

            // Cairkan escrow estimasi: sisa dana kembali ke dompet customer otomatis
            if ($heldIntent !== null) {
                $this->captureEstimate($estimate, $heldIntent, $serviceCost, $sparepartCost, $finalTotal, $heldAmount);
            }

            return $booking->fresh();
        });
    }

    private function heldIntent(Estimate $estimate): ?PaymentIntent
    {
        return $estimate->paymentIntents()
            ->where('status', PaymentIntentStatus::HELD->value)
            ->latest()
            ->first();
    }

    /**
     * Tentukan pembagian pendapatan lalu capture escrow.
     * Bila biaya aktual < dana ditahan, selisihnya dikembalikan gateway ke dompet customer.
     */
    private function captureEstimate(
        Estimate $estimate,
        PaymentIntent $heldIntent,
        int $serviceCost,
        int $sparepartCost,
        int $finalTotal,
        int $heldAmount,
    ): void {
        if ($finalTotal >= $heldAmount) {
            // Selisih di atas hold sudah ditagih terpisah; escrow dicairkan penuh
            $captureService = $finalTotal > 0 ? intdiv($heldAmount * $serviceCost, $finalTotal) : 0;
            $captureParts = $heldAmount - $captureService;
            $captureAmount = $heldAmount;
        } else {
            $captureService = $serviceCost;
            $captureParts = $sparepartCost;
            $captureAmount = $finalTotal;
        }

        $estimate->update([
            'final_service_total' => $captureService,
            'final_parts_total' => $captureParts,
        ]);

        $this->paymentGateway->capture(
            $heldIntent->fresh(),
            Money::fromIdr($captureAmount),
            'estimate_capture_'.$estimate->uuid
        );
    }
}
