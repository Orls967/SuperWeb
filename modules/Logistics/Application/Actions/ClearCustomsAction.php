<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\CustomsException;
use Modules\Logistics\Domain\Models\CustomsDeclaration;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentException;

class ClearCustomsAction
{
    public function __construct(
        private readonly RecordTrackingEventAction $recordEvent
    ) {}

    /**
     * Alur 13 (langkah 3): petugas meloloskan dokumen (SPPB simulasi). Syarat: bea sudah dibayar.
     * Resi yang ditahan dilepas kembali ke status sebelumnya (PickedUp dilanjutkan sebagai InTransit).
     */
    public function execute(User $officer, CustomsDeclaration $declaration): CustomsDeclaration
    {
        if (! ($officer->isAdmin() || $officer->isLogisticsAdmin())) {
            throw CustomsException::forbidden('meloloskan dokumen kepabeanan');
        }

        return DB::transaction(function () use ($officer, $declaration) {
            $declaration = CustomsDeclaration::whereKey($declaration->id)->lockForUpdate()->firstOrFail();

            if ($declaration->status === CustomsDeclaration::STATUS_CLEARED) {
                throw CustomsException::invalidState($declaration->status, 'submitted/on_hold');
            }

            if ($declaration->paid_at === null) {
                throw CustomsException::notPaid();
            }

            $shipment = Shipment::whereKey($declaration->shipment_id)->lockForUpdate()->firstOrFail();
            $resumedTo = null;

            if ($declaration->status === CustomsDeclaration::STATUS_ON_HOLD && $shipment->status === ShipmentStatus::CustomsHold) {
                $previous = ShipmentStatus::tryFrom((string) $declaration->previous_shipment_status) ?? ShipmentStatus::InTransit;
                $resumedTo = $shipment->status->canTransitionTo($previous) ? $previous : ShipmentStatus::InTransit;
                $shipment->transitionTo($resumedTo);
            }

            $declaration->update([
                'status' => CustomsDeclaration::STATUS_CLEARED,
                'cleared_by' => $officer->id,
                'cleared_at' => now(),
            ]);

            ShipmentException::where('dedupe_key', "customs_hold:{$declaration->id}")
                ->where('status', ShipmentException::STATUS_OPEN)
                ->update([
                    'status' => ShipmentException::STATUS_RESOLVED,
                    'resolved_at' => now(),
                    'resolved_by' => $officer->id,
                    'resolution_notes' => 'Dokumen kepabeanan diloloskan.',
                ]);

            $this->recordEvent->execute(
                shipment: $shipment,
                eventType: 'CUSTOMS_CLEARED',
                actor: $officer,
                description: 'Dokumen bea cukai diloloskan (clearance).',
                payload: ['declaration_id' => $declaration->id, 'resumed_to' => $resumedTo?->value]
            );

            return $declaration;
        });
    }
}
