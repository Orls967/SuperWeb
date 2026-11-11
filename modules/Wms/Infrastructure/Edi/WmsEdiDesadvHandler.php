<?php

declare(strict_types=1);

namespace Modules\Wms\Infrastructure\Edi;

use Modules\Core\Contracts\CanonicalEdiDocument;
use Modules\Core\Contracts\EdiDocumentHandler;
use Modules\Core\Contracts\EdiHandleResult;
use Modules\Wms\Domain\Models\DockAppointment;
use Modules\Wms\Domain\Models\Warehouse;

class WmsEdiDesadvHandler implements EdiDocumentHandler
{
    public function moduleName(): string
    {
        return 'Wms';
    }

    public function supports(string $messageType): bool
    {
        return in_array(strtoupper($messageType), ['DESADV', '856'], true);
    }

    public function handle(CanonicalEdiDocument $document, string $idempotencyKey): EdiHandleResult
    {
        $existing = DockAppointment::where('reference', $document->documentNumber)->first();
        if ($existing !== null) {
            return EdiHandleResult::success((string) $existing->id, 'Dock appointment already scheduled');
        }

        $warehouse = Warehouse::first();
        if (! $warehouse) {
            $warehouse = Warehouse::create([
                'code' => 'WH-EDI-MAIN',
                'name' => 'EDI Main Warehouse',
                'address' => 'Warehouse St. 1',
                'is_active' => true,
            ]);
        }
        $warehouseId = $warehouse->id;

        $appointment = DockAppointment::create([
            'warehouse_id' => $warehouseId,
            'direction' => 'inbound',
            'reference' => $document->documentNumber ?: 'ASN-'.uniqid(),
            'window_start' => now()->addHours(2),
            'window_end' => now()->addHours(6),
            'status' => 'confirmed',
            'carrier' => $document->senderId,
            'notes' => 'Inbound ASN from EDI DESADV interchange '.$document->interchangeControlRef,
        ]);

        return EdiHandleResult::success((string) $appointment->id, 'Inbound dock appointment scheduled from DESADV ASN');
    }
}
