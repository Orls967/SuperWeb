<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Actions\CollectPipelineStatusAction;

class QualityPipelineStatusCommand extends Command
{
    protected $signature = 'quality:pipeline-status';

    protected $description = 'Menampilkan status integrasi pipeline CI/CD dan trunk git';

    public function handle(CollectPipelineStatusAction $action): int
    {
        $status = $action->collect();

        $this->info("Branch: {$status['git_branch']}");
        $this->info("Commit: {$status['commit_hash']}");
        $this->info("CI Gate: {$status['ci_status']}");
        $this->info("Checked: {$status['last_checked']}");

        return 0;
    }
}
