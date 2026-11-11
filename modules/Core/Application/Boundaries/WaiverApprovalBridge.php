<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Str;
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Core\Application\Services\ApprovalEngineService;
use Modules\Core\Domain\Models\ApprovalRequest;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Modules\Core\Domain\Models\CoreTask;

/**
 * Service bridging architecture waivers with ApprovalEngine and TaskInbox (DR4-7b).
 *
 * Implements:
 * - Module owner approval workflow for new waivers and extensions
 * - Automated inbox task generation for upcoming waiver expirations (<= 7 days remaining)
 */
final class WaiverApprovalBridge
{
    public function __construct(
        private readonly ?ApprovalEngineService $approvalEngine = null,
        private readonly ?TaskInboxService $taskInbox = null,
    ) {}

    /**
     * Submit an architecture waiver request for module owner approval.
     */
    public function requestWaiverApproval(
        CoreBoundaryViolation $violation,
        User $requester,
        string $adrAnchor,
        string $reason,
        Carbon $expiresAt
    ): ?ApprovalRequest {
        $engine = $this->resolveApprovalEngine();
        if ($engine === null) {
            return null;
        }

        $days = (int) now()->diffInDays($expiresAt, false);
        if ($days <= 0 || $days > 90) {
            throw new DomainException("Tenggat waktu waiver harus antara 1 dan 90 hari (diminta: {$days} hari).");
        }

        $title = "Persetujuan Waiver Arsitektur: {$violation->rule_id} pada {$violation->client_module} -> {$violation->owner_module}";

        $steps = [
            ['role' => 'lead_architect', 'label' => 'Persetujuan Lead Architect'],
            ['role' => 'module_owner', 'module' => $violation->owner_module, 'label' => "Persetujuan Pemilik Modul {$violation->owner_module}"],
        ];

        $metadata = [
            'violation_id' => $violation->id,
            'rule_id' => $violation->rule_id,
            'adr_anchor' => $adrAnchor,
            'reason' => $reason,
            'expires_at' => $expiresAt->toIso8601String(),
            'requested_by' => $requester->name ?? 'Architect',
        ];

        return $engine->submit(
            'BOUNDARY_WAIVER',
            $title,
            $requester,
            $violation,
            null,
            $steps,
            72, // 72 hours SLA
            $metadata
        );
    }

    /**
     * Notify upcoming expiration of a waiver via TaskInboxService (DR4-7b).
     * Creates an actionable task in "Tugas Saya" for the responsible architect/owner.
     */
    public function notifyUpcomingExpiration(
        CoreBoundaryViolation $waiver,
        int $daysRemaining,
        ?User $assignee = null
    ): ?CoreTask {
        $inbox = $this->resolveTaskInbox();
        if ($inbox === null) {
            return null;
        }

        $title = "Waiver Segera Kedaluwarsa: [{$waiver->rule_id}] {$waiver->signature} ({$daysRemaining} hari tersisa)";
        $description = sprintf(
            'Waiver arsitektur #%d untuk modul %s (pemilik: %s) akan kedaluwarsa pada %s. Segera perpanjang atau selesaikan refaktor kontrak.',
            $waiver->id,
            $waiver->client_module,
            $waiver->owner_module,
            $waiver->expires_at?->format('d M Y') ?? 'segera'
        );

        $taskData = [
            'id' => (string) Str::uuid(),
            'title' => $title,
            'description' => $description,
            'module' => 'Core',
            'source_entity_type' => CoreBoundaryViolation::class,
            'source_entity_id' => (string) $waiver->id,
            'assignee_user_id' => $assignee?->id,
            'assignee_role' => 'platform_architect',
            'status' => 'pending',
            'priority' => $daysRemaining <= 3 ? 'urgent' : 'high',
            'due_at' => $waiver->expires_at?->toDateTimeString(),
            'action_url' => "/admin/architecture/violations/{$waiver->id}",
            'payload' => [
                'waiver_id' => $waiver->id,
                'rule_id' => $waiver->rule_id,
                'days_remaining' => $daysRemaining,
                'adr_anchor' => $waiver->adr_anchor,
            ],
            'creator_id' => null,
        ];

        return $inbox->createTask($taskData);
    }

    /**
     * Scan all active waivers and create tasks for those expiring within $thresholdDays.
     *
     * @return list<CoreTask>
     */
    public function sweepExpiringWaivers(int $thresholdDays = 7): array
    {
        $now = now();
        $targetDate = $now->copy()->addDays($thresholdDays);

        $expiringWaivers = CoreBoundaryViolation::where('status', 'waived')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $targetDate)
            ->get();

        $tasks = [];
        foreach ($expiringWaivers as $waiver) {
            $daysRemaining = max(1, (int) $now->diffInDays($waiver->expires_at));
            $task = $this->notifyUpcomingExpiration($waiver, $daysRemaining);
            if ($task !== null) {
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    private function resolveApprovalEngine(): ?ApprovalEngineService
    {
        if ($this->approvalEngine !== null) {
            return $this->approvalEngine;
        }

        try {
            return app(ApprovalEngineService::class);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveTaskInbox(): ?TaskInboxService
    {
        if ($this->taskInbox !== null) {
            return $this->taskInbox;
        }

        try {
            return app(TaskInboxService::class);
        } catch (\Throwable) {
            return null;
        }
    }
}
