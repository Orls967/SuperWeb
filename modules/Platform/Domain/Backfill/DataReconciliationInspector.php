<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Backfill;

final class DataReconciliationInspector
{
    /**
     * Inspects sample rows between source table and target backfilled column to detect null or mismatched data.
     *
     * @param  list<array{id: int, source_val: mixed, target_val: mixed}>  $sampleRows
     * @return array{reconciled: bool, sampled_count: int, mismatch_count: int, mismatch_ids: list<int>}
     */
    public function inspectSample(array $sampleRows): array
    {
        $mismatchIds = [];

        foreach ($sampleRows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $source = $row['source_val'] ?? null;
            $target = $row['target_val'] ?? null;

            if ($target === null && $source !== null) {
                $mismatchIds[] = $id;
            } elseif ($source !== null && (string) $source !== (string) $target) {
                $mismatchIds[] = $id;
            }
        }

        $sampledCount = count($sampleRows);
        $mismatchCount = count($mismatchIds);

        return [
            'reconciled' => ($mismatchCount === 0),
            'sampled_count' => $sampledCount,
            'mismatch_count' => $mismatchCount,
            'mismatch_ids' => $mismatchIds,
        ];
    }
}
