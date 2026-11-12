<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use Modules\Core\Domain\Access\Lifecycle\BreakGlassEmergencyCoordinator;
use Modules\Core\Domain\Models\CoreAccessGrant;

/**
 * Break-Glass Emergency Privileged Elevation Service (W8-D55).
 *
 * Provides application-level entry points for initiating, approving,
 * revoking, and listing emergency break-glass sessions.
 */
class BreakGlassService
{
    public function __construct(
        private readonly BreakGlassEmergencyCoordinator $coordinator
    ) {}

    /**
     * Request emergency elevation.
     */
    public function initiateBreakGlass(
        string $tenantId,
        int $userId,
        string $justification,
        string $incidentTicketId,
        int $durationMinutes = 120
    ): CoreAccessGrant {
        return $this->coordinator->requestBreakGlass(
            tenantId: $tenantId,
            userId: $userId,
            justification: $justification,
            incidentTicketId: $incidentTicketId,
            ttlMinutes: $durationMinutes
        );
    }

    /**
     * Approve emergency elevation (dual custody).
     */
    public function approveBreakGlass(
        int $grantId,
        int $authorizingOfficerId,
        string $approvalNotes
    ): CoreAccessGrant {
        return $this->coordinator->authorizeBreakGlass(
            grantId: $grantId,
            authorizingOfficerId: $authorizingOfficerId,
            approvalNotes: $approvalNotes
        );
    }

    /**
     * Get active break-glass sessions for a tenant.
     *
     * @return list<CoreAccessGrant>
     */
    public function getActiveSessions(string $tenantId): array
    {
        return CoreAccessGrant::where('tenant_id', $tenantId)
            ->where('scope_type', 'BREAK_GLASS')
            ->where('status', 'ACTIVE')
            ->where('expires_at', '>', now())
            ->with(['transitions'])
            ->get()
            ->all();
    }
}
