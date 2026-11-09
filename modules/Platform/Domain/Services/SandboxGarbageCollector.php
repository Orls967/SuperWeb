<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Enums\SandboxStatus;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\StateMachines\SandboxEnvironmentStateMachine;

/**
 * Garbage Collector periodik pembersih database dan entri sandbox yang kadaluarsa.
 */
class SandboxGarbageCollector
{
    /**
     * @return array{reclaimed_sandboxes: int, bytes_freed: int}
     */
    public function collectGarbage(int $retentionDays = 7): array
    {
        $cutoff = now()->subDays($retentionDays);

        $expired = SandboxEnvironment::where('status', 'expired')
            ->where('updated_at', '<=', $cutoff)
            ->get();

        $count = 0;
        $bytes = 0;

        foreach ($expired as $s) {
            $sm = new SandboxEnvironmentStateMachine($s);
            $sm->transitionTo(SandboxStatus::DESTROYED, 'Reclaimed by garbage collector after retention window');
            $count++;
            $bytes += 1024 * 1024 * 10; // Est. 10MB per schema
        }

        return [
            'reclaimed_sandboxes' => $count,
            'bytes_freed' => $bytes,
        ];
    }
}
