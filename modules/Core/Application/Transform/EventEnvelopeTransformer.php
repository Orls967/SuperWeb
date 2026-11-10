<?php

declare(strict_types=1);

namespace Modules\Core\Application\Transform;

use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Transformator Amplop Event Standar (EventEnvelopeTransformer)
 *
 * Menerjemahkan format pesan internal `SimEventSpine` ke spesifikasi CloudEvents v1.0 standar industri,
 * dan sebaliknya dari payload CloudEvents eksternal ke struktur Spine Envelope internal.
 */
class EventEnvelopeTransformer
{
    public const CLOUDEVENTS_SPEC_VERSION = '1.0';

    /**
     * Mengubah model SimEventSpine internal menjadi CloudEvents JSON envelope.
     *
     * @return array{
     *     specversion: string,
     *     id: string,
     *     source: string,
     *     type: string,
     *     datacontenttype: string,
     *     dataschema: string|null,
     *     time: string,
     *     subject: string,
     *     data: array<string, mixed>,
     *     correlationid: string|null,
     *     causationid: string|null,
     *     aggregatetype: string|null,
     *     aggregatesequence: int
     * }
     */
    public function toCloudEvent(SimEventSpine $event, string $sourceUri = 'urn:typeshit:event-spine'): array
    {
        $payload = (array) $event->payload;
        $schemaVersion = (int) ($event->schema_version ?? 1);
        $dataschemaUri = "urn:typeshit:schema:{$event->topic}:v{$schemaVersion}";

        $time = $event->created_at
            ? $event->created_at->toISOString()
            : gmdate('Y-m-d\TH:i:s\Z');

        return [
            'specversion' => self::CLOUDEVENTS_SPEC_VERSION,
            'id' => (string) ($event->event_id ?? $event->id),
            'source' => $sourceUri,
            'type' => (string) $event->topic,
            'datacontenttype' => 'application/json',
            'dataschema' => $dataschemaUri,
            'time' => $time,
            'subject' => (string) ($event->aggregate_id ?? $event->id),
            'data' => $payload,
            'correlationid' => $event->correlation_id ?? null,
            'causationid' => $event->causation_id ?? null,
            'aggregatetype' => $event->aggregate_type ?? null,
            'aggregatesequence' => (int) ($event->sequence ?? 1),
        ];
    }

    /**
     * Menerjemahkan CloudEvents payload eksternal ke bentuk array siap simpan SimEventSpine.
     *
     * @param  array<string, mixed>  $cloudEvent
     * @return array{
     *     event_id: string,
     *     topic: string,
     *     aggregate_type: string,
     *     aggregate_id: string,
     *     sequence: int,
     *     schema_version: int,
     *     payload: array<string, mixed>,
     *     correlation_id: string|null,
     *     causation_id: string|null
     * }
     */
    public function fromCloudEvent(array $cloudEvent): array
    {
        $id = (string) ($cloudEvent['id'] ?? uniqid('evt_', true));
        $topic = (string) ($cloudEvent['type'] ?? 'generic.untyped.event');
        $subject = (string) ($cloudEvent['subject'] ?? $id);
        $data = (array) ($cloudEvent['data'] ?? []);

        // Parsing schema version dari dataschema URN jika ada
        $schemaVersion = 1;
        $dataschema = $cloudEvent['dataschema'] ?? '';
        if (is_string($dataschema) && preg_match('/:v(\d+)$/', $dataschema, $matches)) {
            $schemaVersion = (int) $matches[1];
        }

        return [
            'event_id' => $id,
            'topic' => $topic,
            'aggregate_type' => (string) ($cloudEvent['aggregatetype'] ?? 'CloudEvent'),
            'aggregate_id' => $subject,
            'sequence' => (int) ($cloudEvent['aggregatesequence'] ?? 1),
            'schema_version' => $schemaVersion,
            'payload' => $data,
            'correlation_id' => isset($cloudEvent['correlationid']) ? (string) $cloudEvent['correlationid'] : null,
            'causation_id' => isset($cloudEvent['causationid']) ? (string) $cloudEvent['causationid'] : null,
        ];
    }
}

