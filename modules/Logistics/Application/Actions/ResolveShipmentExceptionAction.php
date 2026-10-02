<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\InvalidDeliveryOperationException;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentException;

class ResolveShipmentExceptionAction
{
    public function __construct(
        protected RecordTrackingEventAction $recordEvent
    ) {}

    /**
     * Tutup exception. Jika resi sedang berstatus Exception dan tidak ada exception terbuka lain,
     * $resumeTo menentukan status lanjutan (wajib) agar resi tidak tertahan selamanya.
     */
    public function execute(ShipmentException $exception, ?User $resolver, string $notes, ?ShipmentStatus $resumeTo = null): ShipmentException
    {
        return DB::transaction(function () use ($exception, $resolver, $notes, $resumeTo) {
            $exception = ShipmentException::whereKey($exception->id)->lockForUpdate()->firstOrFail();

            if (! $exception->isOpen()) {
                throw new InvalidDeliveryOperationException('Exception ini sudah diselesaikan.');
            }

            $shipment = Shipment::whereKey($exception->shipment_id)->lockForUpdate()->firstOrFail();
            $othersOpen = ShipmentException::where('shipment_id', $shipment->id)
                ->where('status', ShipmentException::STATUS_OPEN)
                ->where('id', '!=', $exception->id)
                ->exists();

            $mustResume = $shipment->status === ShipmentStatus::Exception && ! $othersOpen;

            if ($mustResume) {
                if ($resumeTo === null) {
                    throw new InvalidDeliveryOperationException('Pilih status lanjutan resi (mis. Dalam Perjalanan) untuk menutup exception ini.');
                }
                if ($resumeTo === ShipmentStatus::Exception || ! $shipment->status->canTransitionTo($resumeTo)) {
                    throw new InvalidDeliveryOperationException("Status lanjutan '{$resumeTo->label()}' tidak valid dari status Exception.");
                }
            }

            $exception->update([
                'status' => ShipmentException::STATUS_RESOLVED,
                'resolved_at' => now(),
                'resolved_by' => $resolver?->id,
                'resolution_notes' => mb_substr($notes, 0, 500),
            ]);

            if ($mustResume) {
                $shipment->transitionTo($resumeTo);
            }

            $this->recordEvent->execute(
                shipment: $shipment,
                eventType: 'EXCEPTION_RESOLVED',
                locationId: $exception->location_id,
                actor: $resolver,
                actorRole: $resolver?->role ?? 'system',
                description: "Kendala diselesaikan: {$exception->type->label()}.",
                payload: ['exception_id' => $exception->id, 'type' => $exception->type->value, 'resumed_to' => $mustResume ? $resumeTo->value : null]
            );

            return $exception;
        });
    }
}
