<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Models\PlatformAuditRun;

/**
 * Pembangun laporan rekonsiliasi audit dua sumber platform komprehensif.
 */
class PlatformAuditReportBuilder
{
    public function buildStructuredReport(PlatformAuditRun $run): array
    {
        return [
            'run_uuid' => $run->run_uuid,
            'source_a' => [
                'type' => 'relational_database',
                'status' => $run->source_a_status,
            ],
            'source_b' => [
                'type' => 'physical_filesystem_and_git',
                'status' => $run->source_b_status,
            ],
            'discrepancy_count' => $run->discrepancy_count,
            'findings' => $run->findings ?? [],
            'verdict' => $run->discrepancy_count === 0 ? 'PRISTINE_INTEGRITY' : 'COMPROMISED_INTEGRITY',
            'completed_at' => $run->completed_at?->toIso8601String() ?? date('c'),
        ];
    }
}

