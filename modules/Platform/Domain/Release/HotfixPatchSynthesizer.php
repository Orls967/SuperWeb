<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

use Modules\Platform\Domain\Models\ReleaseHotfix;

final class HotfixPatchSynthesizer
{
    /**
     * Synthesizes isolated patch bundle metadata for an emergency hotfix.
     *
     * @return array{patch_tag: string, branch: string, isolation_verified: bool, cherry_pick_commit: string}
     */
    public function synthesizePatchBundle(ReleaseHotfix $hotfix, string $commitSha): array
    {
        $train = $hotfix->releaseTrain;
        $branchName = "hotfix/{$train->train_code}/{$hotfix->hotfix_number}";
        $patchTag = "patch-{$hotfix->hotfix_number}";

        return [
            'patch_tag' => $patchTag,
            'branch' => $branchName,
            'isolation_verified' => $hotfix->isolation_status === 'verified',
            'cherry_pick_commit' => $commitSha,
        ];
    }
}
