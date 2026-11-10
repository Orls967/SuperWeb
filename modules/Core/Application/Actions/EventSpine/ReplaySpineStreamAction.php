<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\EventSpine;

use Modules\Core\Application\Services\SpineReplayService;

class ReplaySpineStreamAction
{
    public function __construct(
        protected SpineReplayService $replayService
    ) {}

    /**
     * @return array{events_count: int, projection: array<string, mixed>, projection_hash: string}
     */
    public function execute(string $group, string $topic = '*', int $fromOffset = 0, ?int $toOffset = null): array
    {
        return $this->replayService->replay($group, $topic, $fromOffset, $toOffset);
    }
}
