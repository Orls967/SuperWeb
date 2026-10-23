<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts;

use Modules\Core\Application\DTOs\EpodSubmissionDto;
use Modules\Core\Application\DTOs\MobileFieldActionDto;
use Modules\Core\Application\DTOs\MobileSyncEventDto;
use Modules\Core\Domain\Models\CoreMobileEpodRecord;
use Modules\Core\Domain\Models\CoreMobileFieldAction;

interface MobileOfflineSyncInterface
{
    /**
     * Process a batch of offline synchronization events with idempotency and LWW-TG conflict resolution.
     *
     * @param  list<MobileSyncEventDto>  $events
     * @return array{
     *     total: int,
     *     applied: int,
     *     skipped_duplicates: int,
     *     resolved_conflicts: int,
     *     results: list<array<string, mixed>>
     * }
     */
    public function processBatch(array $events): array;

    /**
     * Submit and record an electronic proof of delivery (e-POD) from driver mobile device.
     */
    public function submitEpod(EpodSubmissionDto $dto): CoreMobileEpodRecord;

    /**
     * Record a field action across one of the 4 key roles (operator, hub_operator, driver, agent).
     */
    public function recordFieldAction(MobileFieldActionDto $dto): CoreMobileFieldAction;
}
