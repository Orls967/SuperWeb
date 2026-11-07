<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

final class TurnstileIoTProtocolAdapter
{
    public const STX = "\x02";

    public const ETX = "\x03";

    public const ACK = "\x06";

    public const NAK = "\x15";

    public const CMD_POLL = 'POLL';

    public const CMD_UNLOCK = 'UNLOCK';

    public const CMD_LOCKDOWN = 'LOCK';

    public const CMD_STATUS = 'STAT';

    public const CMD_HEARTBEAT = 'PING';

    public function __construct(
        private readonly string $sharedSecret = 'ven-hardware-hsm-key-2026'
    ) {}

    /**
     * Encode binary command packet for physical turnstile hardware controller.
     *
     * @param  array<string, mixed>  $payload
     */
    public function encodePacket(string $turnstileCode, string $command, array $payload = []): string
    {
        $nonce = bin2hex(random_bytes(8));
        $timestamp = CarbonImmutable::now()->getTimestamp();

        $body = json_encode([
            'turnstile' => $turnstileCode,
            'cmd' => $command,
            'nonce' => $nonce,
            'ts' => $timestamp,
            'data' => $payload,
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $body, $this->sharedSecret);

        return self::STX.$body.'|'.$signature.self::ETX;
    }

    /**
     * Decode and verify incoming telemetry packet from physical turnstile hardware.
     *
     * @return array{turnstile: string, event: string, ticket_token: ?string, direction: string, timestamp: int, verified: bool}
     */
    public function decodePacket(string $rawPacket): array
    {
        if (! str_starts_with($rawPacket, self::STX) || ! str_ends_with($rawPacket, self::ETX)) {
            throw new RuntimeException('Malformed packet frame: missing STX/ETX delimiters.');
        }

        $content = substr($rawPacket, 1, -1);
        $pipePos = strrpos($content, '|');

        if ($pipePos === false) {
            throw new RuntimeException('Malformed packet frame: missing signature separator.');
        }

        $body = substr($content, 0, $pipePos);
        $providedSig = substr($content, $pipePos + 1);

        $expectedSig = hash_hmac('sha256', $body, $this->sharedSecret);
        if (! hash_equals($expectedSig, $providedSig)) {
            throw new RuntimeException('Cryptographic integrity failure: invalid turnstile packet signature.');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return [
            'turnstile' => (string) ($data['turnstile'] ?? 'UNKNOWN'),
            'event' => (string) ($data['event'] ?? 'PULSE'),
            'ticket_token' => isset($data['ticket_token']) ? (string) $data['ticket_token'] : null,
            'direction' => (string) ($data['direction'] ?? 'IN'),
            'timestamp' => (int) ($data['ts'] ?? CarbonImmutable::now()->getTimestamp()),
            'verified' => true,
        ];
    }

    /**
     * Parse pulse duration and solenoid relay hold time based on gate ingress speed.
     */
    public function calculateRelayHoldMs(int $currentQueueDensity): int
    {
        // Standar normal 3000ms; saat antrean padat dikurangi ke 1800ms untuk rotasi cepat
        if ($currentQueueDensity > 80) {
            return 1800;
        }

        if ($currentQueueDensity > 40) {
            return 2400;
        }

        return 3000;
    }

    /**
     * Format response ACK with hardware sequence counter.
     */
    public function acknowledge(string $turnstileCode, int $sequence): string
    {
        $payload = json_encode(['ack' => $sequence, 'turnstile' => $turnstileCode]);
        $sig = hash_hmac('sha256', (string) $payload, $this->sharedSecret);

        return self::ACK.$payload.'|'.$sig.self::ETX;
    }
}
