<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\DesignSystem;

use Modules\Core\Domain\Models\UxFinding;
use Modules\Core\Domain\Models\UxIteration;

class UxResearchAction
{
    /**
     * Merekam satu iterasi riset uji keterpakaian (Usability Test).
     */
    public function recordIteration(string $iterationName, int $taskCount, int $successCount): UxIteration
    {
        $rateBps = $taskCount > 0 ? (int) floor(($successCount * 10000) / $taskCount) : 0;

        return UxIteration::create([
            'iteration_name' => $iterationName,
            'task_count' => $taskCount,
            'success_count' => $successCount,
            'task_success_rate_bps' => $rateBps,
            'conducted_at' => now(),
        ]);
    }

    /**
     * Mencatat temuan observasi riset UX.
     */
    public function logFinding(
        string $findingCode,
        string $moduleCode,
        string $severity,
        string $description,
        ?int $componentId = null
    ): UxFinding {
        return UxFinding::create([
            'finding_code' => $findingCode,
            'module_code' => $moduleCode,
            'severity' => $severity,
            'description' => $description,
            'status' => 'OPEN',
            'component_id' => $componentId,
        ]);
    }

    /**
     * Memindahkan temuan riset ke status BACKLOG atau REMEDIATED.
     */
    public function updateFindingStatus(int $findingId, string $status): UxFinding
    {
        $finding = UxFinding::findOrFail($findingId);
        $finding->update([
            'status' => in_array($status, ['OPEN', 'BACKLOG', 'REMEDIATED'], true) ? $status : 'BACKLOG',
        ]);

        return $finding;
    }
}
