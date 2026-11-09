<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

use Modules\Platform\Domain\Models\ReleaseHotfix;

final class HotfixImpactQuarantineManager
{
    /**
     * Evaluates hotfix isolation readiness before allowing merge into active release train.
     *
     * @return array{can_merge: bool, status: string, blocking_reasons: list<string>}
     */
    public function evaluateQuarantine(ReleaseHotfix $hotfix): array
    {
        $blockingReasons = [];

        if ($hotfix->approved_by === null) {
            $blockingReasons[] = 'Persetujuan resmi release manager wajib diberikan sebelum isolasi dicabut.';
        }

        if ($hotfix->isolation_status === 'quarantined') {
            $blockingReasons[] = 'Hotfix masih dalam status karantina pengujian regresi virtual.';
        }

        $canMerge = ($blockingReasons === [] && $hotfix->isolation_status === 'verified');

        return [
            'can_merge' => $canMerge,
            'status' => $hotfix->isolation_status,
            'blocking_reasons' => $blockingReasons,
        ];
    }
}
