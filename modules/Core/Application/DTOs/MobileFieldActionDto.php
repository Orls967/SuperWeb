<?php

declare(strict_types=1);

namespace Modules\Core\Application\DTOs;

final readonly class MobileFieldActionDto
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $actionCode,
        public string $roleScope,
        public string $actionType,
        public string $referenceId,
        public string $operatorName,
        public array $details,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            actionCode: (string) ($data['action_code'] ?? 'ACT-'.strtoupper(bin2hex(random_bytes(6)))),
            roleScope: (string) ($data['role_scope'] ?? 'operator'),
            actionType: (string) ($data['action_type'] ?? 'WORK_ORDER_LOG'),
            referenceId: (string) ($data['reference_id'] ?? 'REF-001'),
            operatorName: (string) ($data['operator_name'] ?? 'Petugas Lapangan'),
            details: (array) ($data['details'] ?? []),
        );
    }
}
