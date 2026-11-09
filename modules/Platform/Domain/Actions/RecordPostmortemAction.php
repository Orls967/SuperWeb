<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use DateTimeInterface;
use Modules\Platform\Domain\Enums\PostmortemSeverity;
use Modules\Platform\Domain\Models\PostmortemRecord;

class RecordPostmortemAction
{
    /**
     * @param  list<string>  $actionItems
     */
    public function execute(
        string $incidentRef,
        string $title,
        string $rootCause,
        int $authorId,
        PostmortemSeverity $severity = PostmortemSeverity::SEV2_MAJOR,
        ?array $actionItems = null,
        ?DateTimeInterface $startedAt = null,
        ?DateTimeInterface $resolvedAt = null
    ): PostmortemRecord {
        return PostmortemRecord::create([
            'incident_ref' => $incidentRef,
            'severity' => $severity->value,
            'title' => $title,
            'root_cause' => $rootCause,
            'action_items' => $actionItems ?? [],
            'started_at' => $startedAt ?? now()->subHour(),
            'resolved_at' => $resolvedAt ?? now(),
            'author_id' => $authorId,
        ]);
    }
}
