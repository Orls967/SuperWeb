<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Audit;

final class PlatformReleaseAuditRegistry
{
    /**
     * @var list<string>
     */
    private const AUDIT_CATEGORIES = [
        'CHANGE_REQUEST_LIFECYCLE',
        'CAB_QUORUM_VERIFICATION',
        'RELEASE_TRAIN_MANIFEST_INTEGRITY',
        'CANARY_STATISTICAL_COMPLIANCE',
        'BACKFILL_IDEMPOTENCY_CHECKPOINT',
        'MIGRATION_DDL_LOCK_SAFETY',
    ];

    /**
     * @return list<string>
     */
    public function getCategories(): array
    {
        return self::AUDIT_CATEGORIES;
    }
}
