<?php

declare(strict_types=1);

namespace Modules\Core\Application\Dtos;

use DateTimeInterface;

final readonly class EpodSubmissionDto
{
    public function __construct(
        public string $epodCode,
        public string $shipmentRef,
        public string $driverRef,
        public string $recipientName,
        public ?string $recipientRole,
        public string $signaturePayloadB64,
        public string $photoDataB64,
        public ?float $latitude,
        public ?float $longitude,
        public DateTimeInterface $deliveredOfflineAt,
    ) {}

    /**
     * Compute SHA-256 hash for electronic proof of delivery signature.
     */
    public function calculateSignatureHash(): string
    {
        $material = sprintf(
            '%s|%s|%s|%s',
            $this->shipmentRef,
            $this->recipientName,
            $this->signaturePayloadB64,
            $this->deliveredOfflineAt->format('Y-m-d H:i:s')
        );

        return hash('sha256', $material);
    }

    /**
     * Compute SHA-256 hash for photo proof data.
     */
    public function calculatePhotoHash(): string
    {
        return hash('sha256', $this->photoDataB64);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $delivered = $data['delivered_offline_at'] ?? now();
        if (is_string($delivered)) {
            $delivered = new \DateTimeImmutable($delivered);
        }

        return new self(
            epodCode: (string) ($data['epod_code'] ?? 'EPOD-'.strtoupper(bin2hex(random_bytes(6)))),
            shipmentRef: (string) ($data['shipment_ref'] ?? 'SHP-'.bin2hex(random_bytes(4))),
            driverRef: (string) ($data['driver_ref'] ?? 'DRV-001'),
            recipientName: (string) ($data['recipient_name'] ?? 'Budi Santoso'),
            recipientRole: isset($data['recipient_role']) ? (string) $data['recipient_role'] : null,
            signaturePayloadB64: (string) ($data['signature_payload_b64'] ?? 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4='),
            photoDataB64: (string) ($data['photo_data_b64'] ?? 'data:image/jpeg;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : -6.2088,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : 106.8456,
            deliveredOfflineAt: $delivered instanceof DateTimeInterface ? $delivered : now(),
        );
    }
}
