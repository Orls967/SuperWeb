<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface ApprovalEngineInterface
{
    /**
     * Create an approval request with defined multi-level steps.
     *
     * @param  array<array{role?: string, user_id?: int}>  $steps
     */
    public function submit(
        string $approvalType,
        string $title,
        User $creator,
        ?Model $approvable = null,
        ?float $amount = null,
        array $steps = [],
        ?int $slaHours = 48,
        array $metadata = []
    ): object;

    /**
     * Approve the current step in the workflow. Enforces four-eyes principle (approver != creator).
     */
    public function approve(
        int|object $approval,
        User $approver,
        ?string $comments = null
    ): object;

    /**
     * Reject the approval request.
     */
    public function reject(
        int|object $approval,
        User $approver,
        string $reason
    ): object;

    /**
     * Delegate approval step to another user.
     */
    public function delegate(
        int|object $approval,
        User $currentUser,
        User $delegateeUser,
        ?string $reason = null
    ): object;

    /**
     * Escalate an overdue approval request.
     */
    public function escalate(int|object $approval, ?string $reason = null): object;
}
