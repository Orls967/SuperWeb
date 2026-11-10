<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Audit;

use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Pendeteksi Manipulasi Payload Event (EventPayloadTamperDetector)
 *
 * Menggunakan HMAC-SHA256 untuk memverifikasi integritas payload pesan di tabel database.
 * Mencegah SQL injection langsung atau modifikasi liar yang dapat merusak audit trail keuangan/medis.
 */
class EventPayloadTamperDetector
{
    protected string $secretKey;

    public function __construct(?string $secretKey = null)
    {
        $this->secretKey = $secretKey ?? config('app.key', 'default-spine-tamper-secret-key-32b');
    }

    /**
     * Menghitung tanda tangan HMAC-SHA256 kanonikal dari event spine.
     *
     * @param  array<string, mixed>  $payload
     */
    public function computeSignature(string $topic, string $aggregateId, int $sequence, array $payload): string
    {
        $canonicalPayload = $this->canonicalizeJson($payload);
        $message = "{$topic}|{$aggregateId}|{$sequence}|{$canonicalPayload}";

        return hash_hmac('sha256', $message, $this->secretKey);
    }

    /**
     * Memverifikasi apakah event spine memiliki tanda tangan valid dan tidak mengalami manipulasi.
     */
    public function verifyEvent(SimEventSpine $event, ?string $expectedSignature = null): bool
    {
        $payload = (array) $event->payload;
        $aggregateId = (string) ($event->aggregate_id ?? $event->id);
        $sequence = (int) ($event->sequence ?? 1);

        $computed = $this->computeSignature($event->topic, $aggregateId, $sequence, $payload);

        if ($expectedSignature !== null) {
            return hash_equals($expectedSignature, $computed);
        }

        // Jika event menyimpan signature di payload metadata atau header
        $signatureInPayload = $payload['_tamper_signature'] ?? null;
        if (is_string($signatureInPayload)) {
            // Hapus signature dari payload sebelum verifikasi
            $cleanPayload = $payload;
            unset($cleanPayload['_tamper_signature']);
            $computed = $this->computeSignature($event->topic, $aggregateId, $sequence, $cleanPayload);

            return hash_equals($signatureInPayload, $computed);
        }

        return true;
    }

    /**
     * Memindai sekumpulan event spine untuk mendeteksi rekaman yang mengalami korupsi/manipulasi.
     *
     * @param  iterable<SimEventSpine>  $events
     * @return array<int, array{event_id: int|string, topic: string, reason: string}>
     */
    public function scanTampering(iterable $events): array
    {
        $tampered = [];

        foreach ($events as $event) {
            $payload = (array) $event->payload;
            $signature = $payload['_tamper_signature'] ?? null;

            if ($signature !== null && ! $this->verifyEvent($event)) {
                $tampered[] = [
                    'event_id' => $event->id,
                    'topic' => $event->topic,
                    'reason' => 'HMAC signature mismatch: payload atau header terindikasi dimanipulasi secara tidak sah.',
                ];
            }
        }

        return $tampered;
    }

    /**
     * Membubuhkan signature tamper-evident ke dalam payload event sebelum disimpan.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function signPayload(string $topic, string $aggregateId, int $sequence, array $payload): array
    {
        unset($payload['_tamper_signature']);
        $sig = $this->computeSignature($topic, $aggregateId, $sequence, $payload);
        $payload['_tamper_signature'] = $sig;

        return $payload;
    }

    /**
     * Mengurutkan kunci array secara rekursif leksikografis untuk menghasilkan representasi kanonikal.
     *
     * @param  array<string, mixed>  $data
     */
    protected function canonicalizeJson(array $data): string
    {
        $this->recursiveKsort($data);

        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function recursiveKsort(array &$data): void
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->recursiveKsort($value);
            }
        }
    }
}

