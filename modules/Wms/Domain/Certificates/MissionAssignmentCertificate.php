<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

/**
 * Sertifikat verifikasi penugasan robot-tugas oleh J2 RobotTaskAllocator via HungarianSolver.
 */
final readonly class MissionAssignmentCertificate
{
    /**
     * @param  array<int, array{robot_id:int, task_code:string, cost:int}>  $assignments
     */
    public function __construct(
        public array $assignments,
        public int $totalCost,
        public bool $solverVerified,
        public string $hash,
    ) {}

    public function isValid(): bool
    {
        if (! $this->solverVerified || $this->totalCost < 0) {
            return false;
        }

        $sum = 0;
        $seenRobots = [];
        $seenTasks = [];
        foreach ($this->assignments as $item) {
            if (isset($seenRobots[$item['robot_id']]) || isset($seenTasks[$item['task_code']])) {
                return false;
            }
            $seenRobots[$item['robot_id']] = true;
            $seenTasks[$item['task_code']] = true;
            $sum += $item['cost'];
        }

        return $sum === $this->totalCost && $this->hash === self::computeHash($this->assignments, $this->totalCost);
    }

    /**
     * @param  array<int, array{robot_id:int, task_code:string, cost:int}>  $assignments
     */
    public static function computeHash(array $assignments, int $totalCost): string
    {
        return hash('sha256', json_encode(['assignments' => $assignments, 'total_cost' => $totalCost], JSON_THROW_ON_ERROR));
    }
}
