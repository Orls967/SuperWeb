<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

use Modules\Platform\Domain\Models\ChangeRollbackPlan;

final class ChangeReversalPlaybookCompiler
{
    /**
     * Compiles human and machine executable instructions for immediate change rollback.
     *
     * @return array{estimated_rto_seconds: int, automated_executable: bool, steps: list<array{order: int, action: string, command: string, verification: string}>}
     */
    public function compilePlaybook(ChangeRollbackPlan $plan): array
    {
        $steps = [];
        $order = 1;

        switch ($plan->strategy) {
            case 'feature_flag_disable':
                $steps[] = [
                    'order' => $order++,
                    'action' => 'Nonaktifkan flag fitur melalui Redis KV store',
                    'command' => 'php artisan platform:flag-toggle --flag=CHANGE_ENABLED --state=off',
                    'verification' => 'curl -I https://internal.gateway.local/health/flags/CHANGE_ENABLED',
                ];
                break;

            case 'blue_green_switch':
                $steps[] = [
                    'order' => $order++,
                    'action' => 'Alihkan rute ingress traffic dari Green ke Blue pod pool',
                    'command' => 'kubectl patch service platform-ingress -p \'{"spec":{"selector":{"version":"blue"}}}\'',
                    'verification' => 'kubectl get pods -l version=blue --field-selector status.phase=Running',
                ];
                break;

            case 'git_revert':
                $steps[] = [
                    'order' => $order++,
                    'action' => 'Buat commit git revert dan picu emergency fast-track build',
                    'command' => 'git revert -m 1 HEAD --no-edit && git push origin master',
                    'verification' => 'gh run watch $(gh run list -L 1 --json databaseId -q ".[0].databaseId")',
                ];
                break;

            default:
                $steps[] = [
                    'order' => $order++,
                    'action' => 'Eksekusi manual rollback checklist',
                    'command' => 'echo "Rollback manual dibutuhkan"',
                    'verification' => 'php artisan platform:audit',
                ];
        }

        return [
            'estimated_rto_seconds' => $plan->estimated_rto_seconds,
            'automated_executable' => $plan->automated,
            'steps' => $steps,
        ];
    }
}
