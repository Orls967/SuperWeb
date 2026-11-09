<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\Models\ReleaseHotfix;
use Modules\Platform\Domain\Models\ReleaseTrain;

final class InjectReleaseHotfixAction
{
    public function execute(ReleaseTrain $train, ?ChangeRequest $cr, string $hotfixNumber, string $severity, int $approverId): ReleaseHotfix
    {
        return ReleaseHotfix::create([
            'hotfix_number' => $hotfixNumber,
            'release_train_id' => $train->id,
            'change_request_id' => $cr?->id,
            'severity' => $severity,
            'approved_by' => $approverId,
            'isolation_status' => 'verified',
        ]);
    }
}
