<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Values;

use DateTimeImmutable;

/**
 * Immutable Evaluation Audit Log Entry (W8-D53, W8-D60).
 *
 * Captures point-in-time snapshot of an ABAC evaluation for tamper-evident compliance audit trail.
 */
final class EvaluationAuditLogEntry
{
    /**
     * @param  string  $logId  Unique log UUID or hash
     * @param  DateTimeImmutable  $evaluatedAt  Exact time of evaluation
     * @param  string  $tenantId  Active tenant
     * @param  string  $subjectId  Requesting user or principal
     * @param  string  $resourceType  Target entity type
     * @param  string  $resourceId  Target entity ID
     * @param  string  $actionCode  Action verb
     * @param  string  $effect  Decision effect (PERMIT / DENY)
     * @param  string  $reason  Evaluation summary
     * @param  string  $decisionHash  Cryptographic signature of decision
     * @param  array<string, mixed>  $contextSnapshot  Evaluated attributes snapshot
     */
    public function __construct(
        public readonly string $logId,
        public readonly DateTimeImmutable $evaluatedAt,
        public readonly string $tenantId,
        public readonly string $subjectId,
        public readonly string $resourceType,
        public readonly string $resourceId,
        public readonly string $actionCode,
        public readonly string $effect,
        public readonly string $reason,
        public readonly string $decisionHash,
        public readonly array $contextSnapshot = []
    ) {}

    /**
     * Factory from AccessDecision and contexts.
     */
    public static function fromDecision(
        AccessDecision $decision,
        AccessSubjectContext $subject,
        ResourceDescriptor $resource,
        ActionDescriptor $action
    ): self {
        $now = new DateTimeImmutable;
        $id = 'LOG-'.strtoupper(hash('crc32b', microtime().$subject->subjectId));

        return new self(
            logId: $id,
            evaluatedAt: $now,
            tenantId: $subject->tenantId,
            subjectId: $subject->subjectId,
            resourceType: $resource->resourceType,
            resourceId: $resource->resourceId,
            actionCode: $action->actionCode,
            effect: $decision->effect,
            reason: $decision->decisionReason,
            decisionHash: $decision->decisionHash,
            contextSnapshot: [
                'subject_roles' => $subject->roles,
                'clearance' => $subject->clearanceLevel,
                'mfa_verified' => $subject->isMfaVerified,
                'classification' => $resource->classification,
            ]
        );
    }

    /**
     * Convert to array for database insertion or stream publishing.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'log_id' => $this->logId,
            'evaluated_at' => $this->evaluatedAt->format('c'),
            'tenant_id' => $this->tenantId,
            'subject_id' => $this->subjectId,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'action_code' => $this->actionCode,
            'effect' => $this->effect,
            'reason' => $this->reason,
            'decision_hash' => $this->decisionHash,
            'context_snapshot' => $this->contextSnapshot,
        ];
    }
}
