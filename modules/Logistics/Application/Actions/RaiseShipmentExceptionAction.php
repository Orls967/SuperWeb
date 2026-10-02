<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\ExceptionSeverity;
use Modules\Logistics\Domain\Enums\ExceptionType;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentException;

class RaiseShipmentExceptionAction
{
    public function __construct(
        protected RecordTrackingEventAction $recordEvent
    ) {}

    /**
     * Catat exception terstruktur pada resi. Bila $dedupeKey sudah ada, exception lama dikembalikan
     * (idempoten, aman dipanggil ulang oleh deteksi otomatis).
     *
     * Dengan $markShipment=true (pelaporan manual) resi dengan tipe pemblokir dipindah ke status
     * Exception dan kejadian dicatat pada chain of custody.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        Shipment $shipment,
        ExceptionType $type,
        string $description,
        ?User $reporter = null,
        ?ExceptionSeverity $severity = null,
        ?int $locationId = null,
        ?string $dedupeKey = null,
        array $payload = [],
        bool $markShipment = false
    ): ShipmentException {
        if ($dedupeKey !== null && ($existing = ShipmentException::where('dedupe_key', $dedupeKey)->first())) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($shipment, $type, $description, $reporter, $severity, $locationId, $dedupeKey, $payload, $markShipment) {
                $exception = ShipmentException::create([
                    'shipment_id' => $shipment->id,
                    'type' => $type,
                    'severity' => $severity ?? $type->defaultSeverity(),
                    'status' => ShipmentException::STATUS_OPEN,
                    'dedupe_key' => $dedupeKey,
                    'description' => mb_substr($description, 0, 500),
                    'location_id' => $locationId,
                    'reported_by' => $reporter?->id,
                    'payload' => $payload ?: null,
                    'detected_at' => now(),
                ]);

                if ($markShipment) {
                    $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

                    if ($type->blocksShipment() && $shipment->status !== ShipmentStatus::Exception && $shipment->status->canTransitionTo(ShipmentStatus::Exception)) {
                        $shipment->transitionTo(ShipmentStatus::Exception);
                    }

                    $this->recordEvent->execute(
                        shipment: $shipment,
                        eventType: 'EXCEPTION_RAISED',
                        locationId: $locationId,
                        actor: $reporter,
                        description: "Kendala dilaporkan: {$type->label()}.",
                        payload: ['exception_id' => $exception->id, 'type' => $type->value, 'severity' => $exception->severity->value]
                    );
                }

                return $exception;
            });
        } catch (UniqueConstraintViolationException) {
            // Dua proses mendeteksi bersamaan: pemenang sudah menyimpan, kembalikan miliknya.
            return ShipmentException::where('dedupe_key', $dedupeKey)->firstOrFail();
        }
    }
}
