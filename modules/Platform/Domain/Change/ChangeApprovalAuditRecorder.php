<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

use Modules\Platform\Domain\Models\ChangeRequest;

final class ChangeApprovalAuditRecorder
{
    /**
     * Records cryptographically verifiable approval token in change request metadata.
     */
    public function recordApproval(ChangeRequest $cr, int $approverId, string $role, string $reason): void
    {
        $timestamp = now()->toIso8601String();
        $tokenPayload = "{$cr->reference_number}:{$approverId}:{$role}:{$timestamp}";
        $signature = hash_hmac('sha256', $tokenPayload, 'platform-approval-secret-key-v1');

        $metadata = $cr->metadata ?? [];
        $approvals = $metadata['approvals'] ?? [];

        $approvals[] = [
            'approver_id' => $approverId,
            'role' => $role,
            'reason' => $reason,
            'timestamp' => $timestamp,
            'digital_token' => $signature,
        ];

        $metadata['approvals'] = $approvals;
        $cr->metadata = $metadata;
        $cr->save();
    }
}
