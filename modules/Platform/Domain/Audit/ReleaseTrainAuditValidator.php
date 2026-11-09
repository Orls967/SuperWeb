<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Audit;

use Modules\Platform\Domain\Models\ReleaseTrain;

final class ReleaseTrainAuditValidator
{
    /**
     * Audits release trains against departure and hotfix governance rules.
     *
     * @return list<string>
     */
    public function auditAll(): array
    {
        $violations = [];
        $trains = ReleaseTrain::with(['hotfixes', 'changeRequests'])->get();

        foreach ($trains as $train) {
            // Invariant 1: Active train cannot contain unresolved hotfix
            if ($train->status === 'active') {
                foreach ($train->hotfixes as $hf) {
                    if ($hf->isolation_status === 'quarantined') {
                        $violations[] = "Release Train {$train->train_code} aktif namun masih memuat hotfix terkarantina ({$hf->hotfix_number}).";
                    }
                }
            }

            // Invariant 2: Frozen train freeze_at cannot be in the future if status is deploying
            if ($train->status === 'deploying' && $train->freeze_at !== null && $train->freeze_at > now()) {
                $violations[] = "Release Train {$train->train_code} berstatus deploying sebelum batas waktu freeze terlampaui.";
            }
        }

        return $violations;
    }
}
