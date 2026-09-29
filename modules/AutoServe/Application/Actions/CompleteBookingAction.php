<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use Exception;
use Illuminate\Support\Facades\DB;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Shared\Application\BaseAction;

class CompleteBookingAction extends BaseAction
{
    public function execute(Booking $booking, ?string $notes = null): Booking
    {
        return $this->handle($booking, $notes);
    }

    public function handle(Booking $booking, ?string $notes = null): Booking
    {
        return $this->transaction(function () use ($booking, $notes) {
            foreach ($booking->spareparts as $sparepart) {
                $qty = $sparepart->pivot->quantity;

                $affected = DB::table($sparepart->getTable())
                    ->where('id', $sparepart->id)
                    ->where('stock', '>=', $qty)
                    ->decrement('stock', $qty);

                if ($affected === 0) {
                    throw new Exception(
                        "Stok {$sparepart->name} tidak mencukupi! Tersisa: {$sparepart->stock}, dibutuhkan: {$qty}"
                    );
                }
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

            return $booking;
        });
    }
}
