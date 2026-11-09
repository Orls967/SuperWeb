<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Audit;

use Modules\Platform\Domain\Models\DataBackfill;

final class BackfillIntegrityAuditor
{
    /**
     * Verifies that completed backfills processed 100% of declared records without missing chunks.
     *
     * @return list<string>
     */
    public function auditAll(): array
    {
        $violations = [];
        $backfills = DataBackfill::with('chunks')->get();

        foreach ($backfills as $bf) {
            if ($bf->status === 'completed') {
                if ($bf->cursor_pointer < $bf->total_records) {
                    $violations[] = "Backfill {$bf->job_name} berstatus completed tetapi cursor pointer ({$bf->cursor_pointer}) lebih kecil dari total records ({$bf->total_records}).";
                }

                $hasFailedChunk = $bf->chunks->contains('status', 'failed');
                if ($hasFailedChunk) {
                    $violations[] = "Backfill {$bf->job_name} dinyatakan completed tetapi memiliki chunk berstatus failed.";
                }
            }
        }

        return $violations;
    }
}
