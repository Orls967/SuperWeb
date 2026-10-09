<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Domain\Models\ApprovalHistory;
use Modules\Core\Domain\Models\ApprovalRequest;
use Modules\Core\Domain\Models\ApprovalStep;
use RuntimeException;

class ApprovalEngineService implements ApprovalEngineInterface
{
    /**
     * Submit an approval request with defined multi-level steps.
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
    ): ApprovalRequest {
        if (empty($steps)) {
            // Default single-step approval by admin
            $steps = [['role' => 'admin']];
        }

        return DB::transaction(function () use (
            $approvalType,
            $title,
            $creator,
            $approvable,
            $amount,
            $steps,
            $slaHours,
            $metadata
        ) {
            $totalSteps = count($steps);
            $slaDueAt = $slaHours ? now()->addHours($slaHours) : null;

            $approval = ApprovalRequest::create([
                'uuid' => (string) Str::uuid(),
                'approvable_type' => $approvable ? get_class($approvable) : null,
                'approvable_id' => $approvable?->getKey(),
                'approval_type' => strtoupper(trim($approvalType)),
                'title' => trim($title),
                'amount' => $amount,
                'currency' => 'IDR',
                'created_by' => $creator->id,
                'status' => 'pending',
                'current_step' => 1,
                'total_steps' => $totalSteps,
                'sla_due_at' => $slaDueAt,
                'metadata' => $metadata,
            ]);

            foreach ($steps as $index => $stepDef) {
                ApprovalStep::create([
                    'approval_id' => $approval->id,
                    'step_number' => $index + 1,
                    'role_required' => $stepDef['role'] ?? null,
                    'assigned_user_id' => $stepDef['user_id'] ?? null,
                    'status' => 'pending',
                ]);
            }

            ApprovalHistory::create([
                'approval_id' => $approval->id,
                'step_number' => 1,
                'user_id' => $creator->id,
                'action' => 'SUBMITTED',
                'notes' => 'Permintaan persetujuan diajukan.',
                'context' => ['amount' => $amount, 'steps_count' => $totalSteps],
                'created_at' => now(),
            ]);

            return $approval->load('steps');
        });
    }

    /**
     * Approve the current step in the workflow. Enforces four-eyes principle (approver != creator).
     */
    public function approve(
        int|object $approval,
        User $approver,
        ?string $comments = null
    ): ApprovalRequest {
        return DB::transaction(function () use ($approval, $approver, $comments) {
            $id = $approval instanceof ApprovalRequest ? $approval->id : (int) $approval;

            /** @var ApprovalRequest $req */
            $req = ApprovalRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($req->status !== 'pending' && $req->status !== 'escalated') {
                throw new RuntimeException("Permintaan persetujuan tidak dalam status aktif (status: {$req->status}).");
            }

            // Four-eyes principle: approver CANNOT be the creator of the request
            if ($approver->id === $req->created_by) {
                throw new RuntimeException('Prinsip Four-Eyes: Penyetuju tidak boleh orang yang sama dengan pembuat permohonan.');
            }

            /** @var ApprovalStep $currentStep */
            $currentStep = $req->steps()->where('step_number', $req->current_step)->lockForUpdate()->firstOrFail();

            // Authorization check
            if ($currentStep->assigned_user_id && $currentStep->assigned_user_id !== $approver->id) {
                throw new RuntimeException('Langkah ini ditugaskan khusus kepada pengguna lain.');
            }

            if ($currentStep->role_required) {
                $hasRole = $approver->role === $currentStep->role_required
                    || (method_exists($approver, 'hasRbacRole') && $approver->hasRbacRole($currentStep->role_required))
                    || $approver->role === 'admin';

                if (! $hasRole) {
                    throw new RuntimeException("Pengguna tidak memiliki peran yang disyaratkan ({$currentStep->role_required}).");
                }
            }

            // Mark step approved
            $currentStep->update([
                'status' => 'approved',
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'comments' => $comments,
            ]);

            ApprovalHistory::create([
                'approval_id' => $req->id,
                'step_number' => $req->current_step,
                'user_id' => $approver->id,
                'action' => 'APPROVED',
                'notes' => $comments,
                'created_at' => now(),
            ]);

            // Advance workflow or finalize
            if ($req->current_step >= $req->total_steps) {
                $req->update([
                    'status' => 'approved',
                    'decided_at' => now(),
                ]);
            } else {
                $req->increment('current_step');
            }

            return $req->fresh(['steps', 'histories']);
        });
    }

    /**
     * Reject the approval request.
     */
    public function reject(
        int|object $approval,
        User $approver,
        string $reason
    ): ApprovalRequest {
        return DB::transaction(function () use ($approval, $approver, $reason) {
            $id = $approval instanceof ApprovalRequest ? $approval->id : (int) $approval;

            /** @var ApprovalRequest $req */
            $req = ApprovalRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($req->status !== 'pending' && $req->status !== 'escalated') {
                throw new RuntimeException("Permintaan persetujuan tidak dalam status aktif (status: {$req->status}).");
            }

            if ($approver->id === $req->created_by) {
                throw new RuntimeException('Prinsip Four-Eyes: Penyetuju/penolak tidak boleh pembuat permohonan.');
            }

            $currentStep = $req->steps()->where('step_number', $req->current_step)->lockForUpdate()->firstOrFail();
            $currentStep->update([
                'status' => 'rejected',
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'comments' => $reason,
            ]);

            $req->update([
                'status' => 'rejected',
                'decided_at' => now(),
            ]);

            ApprovalHistory::create([
                'approval_id' => $req->id,
                'step_number' => $req->current_step,
                'user_id' => $approver->id,
                'action' => 'REJECTED',
                'notes' => $reason,
                'created_at' => now(),
            ]);

            return $req->fresh(['steps', 'histories']);
        });
    }

    /**
     * Delegate approval step to another user.
     */
    public function delegate(
        int|object $approval,
        User $currentUser,
        User $delegateeUser,
        ?string $reason = null
    ): ApprovalRequest {
        return DB::transaction(function () use ($approval, $currentUser, $delegateeUser, $reason) {
            $id = $approval instanceof ApprovalRequest ? $approval->id : (int) $approval;

            /** @var ApprovalRequest $req */
            $req = ApprovalRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($delegateeUser->id === $req->created_by) {
                throw new InvalidArgumentException('Tidak dapat mendelegasikan wewenang persetujuan kepada pembuat permohonan.');
            }

            $currentStep = $req->steps()->where('step_number', $req->current_step)->lockForUpdate()->firstOrFail();
            $currentStep->update([
                'delegated_to' => $delegateeUser->id,
                'assigned_user_id' => $delegateeUser->id,
            ]);

            ApprovalHistory::create([
                'approval_id' => $req->id,
                'step_number' => $req->current_step,
                'user_id' => $currentUser->id,
                'action' => 'DELEGATED',
                'notes' => "Didelegasikan kepada {$delegateeUser->name}. Alasan: {$reason}",
                'created_at' => now(),
            ]);

            return $req->fresh(['steps', 'histories']);
        });
    }

    /**
     * Escalate an overdue approval request.
     */
    public function escalate(int|object $approval, ?string $reason = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approval, $reason) {
            $id = $approval instanceof ApprovalRequest ? $approval->id : (int) $approval;

            /** @var ApprovalRequest $req */
            $req = ApprovalRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            $req->update([
                'status' => 'escalated',
            ]);

            ApprovalHistory::create([
                'approval_id' => $req->id,
                'step_number' => $req->current_step,
                'user_id' => null,
                'action' => 'ESCALATED',
                'notes' => $reason ?? 'Permintaan persetujuan telah melewati batas waktu SLA dan dieskalasi.',
                'created_at' => now(),
            ]);

            return $req->fresh(['steps', 'histories']);
        });
    }
}
