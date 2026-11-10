<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\EventSpine;

use Modules\Core\Application\Services\DeterministicSnapshotService;

class CreateProjectionSnapshotAction
{
    public function __construct(
        protected DeterministicSnapshotService $snapshotService
    ) {}

    /**
     * @return array{offset: int, state: array<string, mixed>, hash: string}
     */
    public function execute(string $group, int $upToOffset): array
    {
        return $this->snapshotService->takeSnapshot($group, $upToOffset);
    }
}
