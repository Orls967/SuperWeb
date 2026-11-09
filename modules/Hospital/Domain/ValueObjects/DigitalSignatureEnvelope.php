<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\ValueObjects;

final class DigitalSignatureEnvelope
{
    public function __construct(
        public readonly string $signerId,
        public readonly string $sipNumber,
        public readonly string $signedPayloadHash,
        public readonly string $signatureHash,
        public readonly string $rfc3161Timestamp,
        public readonly string $algorithm = 'SHA256withRSA'
    ) {}

    public static function create(
        string $signerId,
        string $sipNumber,
        string $payload,
        string $secretKey
    ): self {
        $timestamp = now()->toIso8601String();
        $payloadHash = hash('sha256', $payload);
        $signature = hash_hmac('sha256', "{$signerId}:{$sipNumber}:{$payloadHash}:{$timestamp}", $secretKey);

        return new self(
            signerId: $signerId,
            sipNumber: $sipNumber,
            signedPayloadHash: $payloadHash,
            signatureHash: $signature,
            rfc3161Timestamp: $timestamp
        );
    }

    public function verify(string $payload, string $secretKey): bool
    {
        $calculatedPayloadHash = hash('sha256', $payload);
        if (! hash_equals($this->signedPayloadHash, $calculatedPayloadHash)) {
            return false;
        }

        $expectedSignature = hash_hmac(
            'sha256',
            "{$this->signerId}:{$this->sipNumber}:{$calculatedPayloadHash}:{$this->rfc3161Timestamp}",
            $secretKey
        );

        return hash_equals($this->signatureHash, $expectedSignature);
    }

    public function toArray(): array
    {
        return [
            'signer_id' => $this->signerId,
            'sip_number' => $this->sipNumber,
            'signed_payload_hash' => $this->signedPayloadHash,
            'signature_hash' => $this->signatureHash,
            'rfc3161_timestamp' => $this->rfc3161Timestamp,
            'algorithm' => $this->algorithm,
        ];
    }
}
