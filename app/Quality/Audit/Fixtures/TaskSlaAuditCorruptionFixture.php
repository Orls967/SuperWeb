<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Carbon\Carbon;
use Modules\Core\Domain\Models\CoreTask;

final class TaskSlaAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'task:sla-audit';
    }

    public function corrupt(): string
    {
        // Suntikkan serangkaian tugas tertunggak (breached) untuk merusak kepatuhan SLA di bawah 80%
        for ($i = 0; $i < 15; $i++) {
            CoreTask::create([
                'module_code' => 'AUDIT_CORRUPTION',
                'title' => 'Tugas Tertunggak Buatan Uji Audit '.$i,
                'status' => 'pending',
                'priority' => 'critical',
                'due_date' => Carbon::now()->subDays(10),
            ]);
        }

        return '15 tugas kedaluwarsa disuntikkan menurunkan rasio kepatuhan SLA';
    }
}
