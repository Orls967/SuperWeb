<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Events;

final class EnterpriseSodViolationDetected
{
    public const EVENT_NAME = 'enterprise.sod.violation_detected.v1';

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $sodRuleId,
        public readonly string $firstRole,
        public readonly string $secondRole,
        public readonly string $severity,
        public readonly array $metadata = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'sod_rule_id' => $this->sodRuleId,
            'first_role' => $this->firstRole,
            'second_role' => $this->secondRole,
            'severity' => $this->severity,
            'metadata' => $this->metadata,
        ];
    }
}

