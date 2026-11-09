<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Models\DataBackfill;

final class DispatchBackfillJobAction
{
    public function execute(string $jobName, string $targetTable, int $totalRecords, int $chunkSize = 100): DataBackfill
    {
        return DataBackfill::create([
            'job_name' => $jobName,
            'target_table' => $targetTable,
            'total_records' => $totalRecords,
            'chunk_size' => $chunkSize,
            'status' => 'pending',
            'cursor_pointer' => 0,
        ]);
    }
}
