<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Core\Domain\Models\CoreTask;
use Modules\Core\Domain\Models\CoreTaskHistory;

final class SearchAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'search:audit';
    }

    public function corrupt(): string
    {
        // Suntikkan ketidakselarasan status tugas aktif vs riwayat mutasi terakhir
        $task = CoreTask::create([
            'id' => 'CORRUPTED-TASK-DUAL-SOURCE-99',
            'module' => 'Core',
            'title' => 'Tugas Korupsi Audit Dua Sumber',
            'source_entity_type' => 'audit_test',
            'source_entity_id' => '99',
            'status' => 'approved',
        ]);

        CoreTaskHistory::create([
            'task_id' => $task->id,
            'action' => 'created',
            'from_status' => null,
            'to_status' => 'pending',
            'notes' => 'Status mutasi riwayat tidak selaras dengan tugas aktif',
        ]);

        return 'Inkonsistensi integritas Task';
    }
}
