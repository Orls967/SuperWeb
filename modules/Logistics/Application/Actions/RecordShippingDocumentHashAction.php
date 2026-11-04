<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TrackingEvent;

/**
 * Mencatat hash dokumen BL/POD ke rantai custody lewat RecordTrackingEventAction yang sudah ada.
 * Satu hash dokumen hanya boleh tercatat satu kali (di pengiriman manapun).
 */
class RecordShippingDocumentHashAction
{
    public function __construct(private readonly RecordTrackingEventAction $recorder) {}

    public static function eventTypeFor(string $documentType): string
    {
        return match (strtoupper($documentType)) {
            'BL' => 'DOCUMENT_HASH_BL',
            'POD' => 'DOCUMENT_HASH_POD',
            default => throw new InvalidArgumentException("Tipe dokumen '{$documentType}' tidak dikenal (BL atau POD)."),
        };
    }

    public function execute(
        Shipment $shipment,
        string $documentType,
        string $sha256Hex,
        ?User $actor = null,
        ?CarbonInterface $occurredAt = null
    ): TrackingEvent {
        $eventType = self::eventTypeFor($documentType);
        $sha256Hex = strtolower($sha256Hex);

        if (! preg_match('/^[0-9a-f]{64}$/', $sha256Hex)) {
            throw new InvalidArgumentException('Hash dokumen harus SHA-256 hex 64 karakter.');
        }

        $existing = TrackingEvent::where('event_type', $eventType)
            ->where('payload->document_hash', $sha256Hex)
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            if ((int) $existing->shipment_id !== (int) $shipment->id) {
                throw new InvalidArgumentException('Hash dokumen sudah tercatat pada pengiriman lain.');
            }

            return $existing;
        }

        return $this->recorder->execute(
            shipment: $shipment,
            eventType: $eventType,
            actor: $actor,
            description: 'Hash dokumen '.strtoupper($documentType).' dicatat ke rantai custody',
            payload: ['document_type' => strtoupper($documentType), 'document_hash' => $sha256Hex],
            occurredAt: $occurredAt
        );
    }
}
