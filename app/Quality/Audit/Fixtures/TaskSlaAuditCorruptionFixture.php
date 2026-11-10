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
        // Suntikkan 15 tugas kedaluwarsa untuk merusak rasio kepatuhan SLA di bawah ambang batas 80%
        for ($i = 0; $i < 15; $i++) {
            CoreTask::create([
                'id' => 'CORRUPT-SLA-'.uniqid().'-'.$i,
                'module' => 'Core',
                'title' => 'Tugas Tertunggak Buatan Uji Audit '.$i,
                'source_entity_type' => 'audit_test',
                'source_entity_id' => (string) $i,
                'status' => 'pending',
                'priority' => 'critical',
                'due_at' => Carbon::now()->subDays(10),
            ]);
        }

        return 'Pelanggaran SLA terdeteksi';
    }
}
