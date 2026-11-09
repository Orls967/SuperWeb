<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

use Carbon\CarbonImmutable;
use Modules\Platform\Domain\Models\ChangeRequest;

final class ReleaseApprovalNotificationService
{
    /**
     * @param  list<string>  $approverEmails
     * @return array{
     *     dispatched_count: int,
     *     target_recipients: list<string>,
     *     scheduled_at: string
     * }
     */
    public function dispatchApprovalRequests(ChangeRequest $changeRequest, array $approverEmails): array
    {
        $recipients = [];

        foreach ($approverEmails as $email) {
            $trimmed = trim(strtolower($email));
            if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $trimmed;
            }
        }

        $now = CarbonImmutable::now()->toIso8601String();

        return [
            'dispatched_count' => count($recipients),
            'target_recipients' => $recipients,
            'scheduled_at' => $now,
        ];
    }

    /**
     * @return array{
     *     summary_line: string,
     *     risk_badge: string,
     *     action_required: string
     * }
     */
    public function formatApprovalDigest(ChangeRequest $changeRequest): array
    {
        $riskBadge = strtoupper($changeRequest->risk_tier->value);
        $summary = "Change Request {$changeRequest->change_number}: {$changeRequest->title} [{$riskBadge}]";
        $action = match ($changeRequest->risk_tier->value) {
            'critical' => 'Mandatory CAB quorum vote required prior to deployment freeze',
            'high' => 'Requires lead architect and security sign-off',
            default => 'Standard peer review and automated gate approval',
        };

        return [
            'summary_line' => $summary,
            'risk_badge' => $riskBadge,
            'action_required' => $action,
        ];
    }
}
