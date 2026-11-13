<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use Modules\Wms\Domain\Certificates\WaveFallbackPlanCertificate;

/**
 * J3 WaveAutomationDispatcher: menyusun rencana dispatch wave robotik
 * dan menentukan mode fallback manual saat armada robot tidak mencukupi.
 */
final class WaveAutomationDispatcher
{
    /**
     * @param  list<array{task_code:string, zone:string, priority:int}>  $tasks
     * @param  list<int>  $availableRobotIds
     * @return array{
     *     wave_code: string,
     *     mode: string,
     *     automated_tasks: list<string>,
     *     manual_fallback_tasks: list<string>,
     *     certificate: WaveFallbackPlanCertificate
     * }
     */
    public function planWaveDispatch(
        string $waveCode,
        array $tasks,
        array $availableRobotIds,
    ): array {
        usort($tasks, static fn (array $a, array $b): int => [$a['priority'], $a['task_code']] <=> [$b['priority'], $b['task_code']]);

        $robotCount = count($availableRobotIds);
        $taskCount = count($tasks);

        if ($robotCount === 0) {
            $mode = 'manual_fallback';
            $automatedTasks = [];
            $manualTasks = array_column($tasks, 'task_code');
        } elseif ($robotCount < $taskCount) {
            $mode = 'hybrid';
            $automatedTasks = array_column(array_slice($tasks, 0, $robotCount), 'task_code');
            $manualTasks = array_column(array_slice($tasks, $robotCount), 'task_code');
        } else {
            $mode = 'automated';
            $automatedTasks = array_column($tasks, 'task_code');
            $manualTasks = [];
        }

        $hash = WaveFallbackPlanCertificate::computeHash($waveCode, $mode, $taskCount, $robotCount);

        return [
            'wave_code' => $waveCode,
            'mode' => $mode,
            'automated_tasks' => $automatedTasks,
            'manual_fallback_tasks' => $manualTasks,
            'certificate' => new WaveFallbackPlanCertificate(
                waveCode: $waveCode,
                mode: $mode,
                taskCount: $taskCount,
                robotCount: $robotCount,
                hash: $hash,
            ),
        ];
    }
}
