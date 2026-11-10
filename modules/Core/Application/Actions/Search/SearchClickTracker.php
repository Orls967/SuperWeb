<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use Modules\Core\Domain\Models\CoreSearchQueryLog;

/**
 * Service pelacak konversi klik hasil pencarian (Click-Through Telemetry & MRR).
 */
class SearchClickTracker
{
    /**
     * Mencatat dokumen yang diklik pengguna dari kueri pencarian tertentu.
     */
    public function recordClick(int $queryLogId, string $clickedDocumentId): bool
    {
        $log = CoreSearchQueryLog::find($queryLogId);
        if ($log === null) {
            return false;
        }

        $log->clicked_result_id = $clickedDocumentId;
        $log->save();

        return true;
    }

    /**
     * Menghitung rasio konversi klik (Click-Through Rate / CTR).
     */
    public function calculateCtr(int $days = 7): float
    {
        $total = CoreSearchQueryLog::where('created_at', '>=', now()->subDays($days))->count();
        if ($total <= 0) {
            return 0.0;
        }

        $clicked = CoreSearchQueryLog::where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('clicked_result_id')
            ->count();

        return round(($clicked / $total) * 100.0, 2);
    }
}
