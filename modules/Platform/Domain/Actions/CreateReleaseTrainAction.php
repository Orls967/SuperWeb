<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use DateTimeImmutable;
use Modules\Platform\Domain\Models\ReleaseTrain;

final class CreateReleaseTrainAction
{
    public function execute(string $trainCode, string $version, int $releaseManagerId, DateTimeImmutable $freezeAt, DateTimeImmutable $scheduledDeployAt): ReleaseTrain
    {
        return ReleaseTrain::create([
            'train_code' => $trainCode,
            'version' => $version,
            'release_manager_id' => $releaseManagerId,
            'status' => 'open',
            'freeze_at' => $freezeAt,
            'scheduled_deploy_at' => $scheduledDeployAt,
        ]);
    }
}
