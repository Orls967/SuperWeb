<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Services;

use InvalidArgumentException;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Logistics\Application\Actions\RecordShippingDocumentHashAction;
use Modules\Logistics\Contracts\CustodyVerifier;
use Modules\Logistics\Domain\Models\TrackingEvent;

/**
 * K6: menghitung ulang hash-chain custody dengan skema yang sama persis dengan `lgx:verify-custody`.
 *
 * Kompleksitas: O(n) terhadap jumlah event pada satu pengiriman (n = event custody shipment itu).
 */
final class CustodyVerifierService implements CustodyVerifier
{
    public function __construct(private readonly EventSpineInterface $spine) {}

    public function verifyDocumentHash(string $documentType, string $sha256Hex): ?array
    {
        $eventType = RecordShippingDocumentHashAction::eventTypeFor($documentType);
        $sha256Hex = strtolower($sha256Hex);

        if (! preg_match('/^[0-9a-f]{64}$/', $sha256Hex)) {
            throw new InvalidArgumentException('Hash dokumen harus SHA-256 hex 64 karakter.');
        }

        $event = TrackingEvent::query()
            ->where('event_type', $eventType)
            ->where('payload->document_hash', $sha256Hex)
            ->orderBy('id')
            ->first();

        if ($event === null) {
            return null;
        }

        $chainValid = $this->chainIsValid((int) $event->shipment_id);

        if ($chainValid) {
            // Sukses pertama per dokumen: kunci idempotensi menjamin sekali saja.
            $this->spine->publish(
                topic: 'logistics.custody',
                eventName: 'lgx.custody.bl_verified.v1',
                payload: [
                    'document_type' => strtoupper($documentType),
                    'document_hash' => $sha256Hex,
                    'shipment_id' => (int) $event->shipment_id,
                    'custody_event_id' => (int) $event->id,
                ],
                idempotencyKey: 'lgx:custody:verified:'.strtoupper($documentType).':'.$sha256Hex,
            );
        }

        return [
            'shipment_id' => (int) $event->shipment_id,
            'custody_event_id' => (int) $event->id,
            'chain_valid' => $chainValid,
        ];
    }

    private function chainIsValid(int $shipmentId): bool
    {
        $events = TrackingEvent::where('shipment_id', $shipmentId)->orderBy('sequence')->get();
        if ($events->isEmpty()) {
            return false;
        }

        $expectedPrev = TrackingEvent::genesisHash($shipmentId);
        $expectedSeq = 1;

        foreach ($events as $event) {
            if ($event->sequence !== $expectedSeq || $event->prev_hash !== $expectedPrev) {
                return false;
            }

            $recalculated = TrackingEvent::calculateHash(
                prevHash: $event->prev_hash,
                sequence: $event->sequence,
                eventType: $event->event_type,
                payload: $event->payload ?? [],
                occurredAt: $event->occurred_at
            );

            if ($recalculated !== $event->hash) {
                return false;
            }

            $expectedPrev = $event->hash;
            $expectedSeq++;
        }

        return true;
    }
}
