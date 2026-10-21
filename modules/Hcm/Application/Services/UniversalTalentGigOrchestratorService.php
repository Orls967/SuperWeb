<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Services;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Hcm\Domain\Models\Gig\UniversalGigTask;
use RuntimeException;

class UniversalTalentGigOrchestratorService
{
    public function __construct(
        protected Ledger $ledger
    ) {}

    public function publishGigTask(
        string $sourceDomain,
        string $title,
        string $description,
        int $hourlyRateIdr,
        int $estimatedHours,
        int $supervisorId,
        ?string $requiredK3Certificate = null
    ): UniversalGigTask {
        $taskCount = UniversalGigTask::query()->count() + 1;
        $taskCode = sprintf('GIG-%s-%05d', strtoupper($sourceDomain), $taskCount);
        $totalCompensation = $hourlyRateIdr * $estimatedHours;

        return UniversalGigTask::query()->create([
            'task_code' => $taskCode,
            'source_domain' => strtoupper($sourceDomain),
            'title' => $title,
            'description' => $description,
            'required_k3_certificate' => $requiredK3Certificate,
            'hourly_rate_idr' => $hourlyRateIdr,
            'estimated_hours' => $estimatedHours,
            'total_compensation_idr' => $totalCompensation,
            'supervisor_user_id' => $supervisorId,
            'status' => 'OPEN',
        ]);
    }

    public function claimGigTask(int $taskId, int $workerId, ?string $workerK3Certificate = null): UniversalGigTask
    {
        $task = UniversalGigTask::query()->findOrFail($taskId);

        if ($task->status !== 'OPEN') {
            throw new RuntimeException("Gig task {$task->task_code} is not open for claims.");
        }

        if ($task->required_k3_certificate !== null && $workerK3Certificate !== $task->required_k3_certificate) {
            throw new RuntimeException("Worker lacks required K3 certification: {$task->required_k3_certificate}");
        }

        $task->update([
            'assigned_user_id' => $workerId,
            'status' => 'CLAIMED',
        ]);

        return $task;
    }

    /**
     * @return array{task_code: string, status: string, net_paid_idr: int, worker_id: int}
     */
    public function approveAndDisburseGigPayout(
        int $taskId,
        int $supervisorId,
        string $completionProofHash
    ): array {
        $task = UniversalGigTask::query()->findOrFail($taskId);

        if ($task->status !== 'COMPLETED' && $task->status !== 'CLAIMED') {
            throw new RuntimeException("Gig task {$task->task_code} must be claimed/completed before payout approval.");
        }

        if ($task->supervisor_user_id !== $supervisorId) {
            throw new RuntimeException('Only the designated supervisor can approve this gig payout.');
        }

        $workerId = $task->assigned_user_id;
        if (! $workerId) {
            throw new RuntimeException('No assigned worker found for this gig.');
        }

        $payoutAmount = $task->total_compensation_idr;
        $domain = strtolower($task->source_domain);
        $idempotencyKey = 'gig_pay:'.$task->task_code.':'.hash('sha256', $completionProofHash);

        $this->ledger->post(new PostingDTO(
            type: 'settlement',
            description: "Gig payout [{$task->source_domain}] for task {$task->task_code}",
            idempotencyKey: $idempotencyKey,
            entries: [
                PostingEntryDTO::forCode("{$domain}:gig_expense:IDR", 'IDR', -$payoutAmount),
                PostingEntryDTO::forCode("payroll:worker_payable:{$workerId}:IDR", 'IDR', $payoutAmount),
            ],
            referenceType: 'hcm_universal_gig_tasks',
            referenceId: (string) $task->id,
        ));

        $task->update(['status' => 'PAID']);

        return [
            'task_code' => $task->task_code,
            'status' => 'PAID',
            'net_paid_idr' => $payoutAmount,
            'worker_id' => $workerId,
        ];
    }
}
