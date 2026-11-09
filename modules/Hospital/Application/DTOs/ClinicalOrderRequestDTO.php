<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class ClinicalOrderRequestDTO
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public readonly int $admissionId,
        public readonly int $patientId,
        public readonly int $doctorUserId,
        public readonly string $orderType, // LAB, PHARMACY, RADIOLOGY, PROCEDURE, DIET
        public readonly string $itemCode,
        public readonly string $itemName,
        public readonly string $urgency, // ROUTINE, URGENT, STAT
        public readonly array $parameters = [],
        public readonly ?string $notes = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            admissionId: (int) ($data['admission_id'] ?? 0),
            patientId: (int) ($data['patient_id'] ?? 0),
            doctorUserId: (int) ($data['doctor_user_id'] ?? 0),
            orderType: strtoupper((string) ($data['order_type'] ?? 'LAB')),
            itemCode: (string) ($data['item_code'] ?? ''),
            itemName: (string) ($data['item_name'] ?? ''),
            urgency: strtoupper((string) ($data['urgency'] ?? 'ROUTINE')),
            parameters: (array) ($data['parameters'] ?? []),
            notes: isset($data['notes']) ? (string) $data['notes'] : null
        );
    }
}
