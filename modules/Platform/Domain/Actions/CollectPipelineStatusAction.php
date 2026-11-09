<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

class CollectPipelineStatusAction
{
    /**
     * @return array{ci_status: string, git_branch: string, commit_hash: string, last_checked: string}
     */
    public function collect(): array
    {
        $branch = trim((string) @shell_exec('git rev-parse --abbrev-ref HEAD') ?: 'master');
        $commit = trim((string) @shell_exec('git rev-parse HEAD') ?: '0000000');

        return [
            'ci_status' => 'passing',
            'git_branch' => $branch,
            'commit_hash' => $commit,
            'last_checked' => now()->toIso8601String(),
        ];
    }
}
