<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\SimRun;

/**
 * Layanan pencatat log audit platform untuk setiap operasi kritis kernel simulasi.
 */
final class SimulationAuditLogPublisherService
{
    public function logOperation(
        SimRun $run,
        string $action,
        string $actorId,
        string $actorRole,
        array $details = [],
    ): void {
        if (DB::getSchemaBuilder()->hasTable('core_audit_logs')) {
            DB::table('core_audit_logs')->insert([
                'user_id' => is_numeric($actorId) ? (int) $actorId : null,
                'action' => "simulation.{$action}",
                'auditable_type' => SimRun::class,
                'auditable_id' => $run->id,
                'old_values' => null,
                'new_values' => json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'SimulationKernel/1.0',
                'created_at' => now(),
            ]);
        }
    }
}
