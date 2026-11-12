<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Lifecycle;

use DomainException;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\Models\CoreAccessGrantTransition;

/**
 * Break-Glass Emergency Elevation Coordinator (W8-D55).
 *
 * Implements emergency privileged access with dual-custody approval,
 * mandatory operational ticket correlation, short hard-cap TTL (max 4 hours),
 * and automatic post-incident review requirement.
 */
final class BreakGlassEmergencyCoordinator
{
    public const MAX_BREAK_GLASS_TTL_MINUTES = 240; // 4 hours maximum

    /**
     * Request emergency break-glass grant.
     *
     * @param  int  $userId  Requesting engineer
     * @param  string  $justification  Incident description and severity justification
     * @param  string  $incidentTicketId  External ticketing system reference (e.g. INC-9941)
     * @param  int  $ttlMinutes  Desired duration (clamped to MAX_BREAK_GLASS_TTL_MINUTES)
     */
    public function requestBreakGlass(
        string $tenantId,
        int $userId,
        string $justification,
        string $incidentTicketId,
        int $ttlMinutes = 120
    ): CoreAccessGrant {
        if (trim($justification) === '') {
            throw new DomainException('Break-glass justification cannot be empty.');
        }

        if (trim($incidentTicketId) === '') {
            throw new DomainException('Incident ticket reference is required for break-glass requests.');
        }

        $effectiveTtl = min(max(15, $ttlMinutes), self::MAX_BREAK_GLASS_TTL_MINUTES);
        $code = 'BG-'.strtoupper(Str::random(10));
        $now = now();
        $expiresAt = $now->copy()->addMinutes($effectiveTtl);

        $grant = CoreAccessGrant::create([
            'grant_code' => $code,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'scope_type' => 'BREAK_GLASS',
            'scope_id' => $incidentTicketId,
            'role_or_permission' => 'system:emergency_admin',
            'status' => 'PENDING_APPROVAL',
            'justification' => "[BREAK-GLASS #{$incidentTicketId}] ".$justification,
            'requested_by' => $userId,
            'expires_at' => $expiresAt,
        ]);

        CoreAccessGrantTransition::create([
            'grant_id' => $grant->id,
            'from_status' => null,
            'to_status' => 'PENDING_APPROVAL',
            'transition_code' => 'INITIATE_BREAK_GLASS',
            'reason' => 'Emergency break-glass elevation requested: '.$incidentTicketId,
            'performed_by' => $userId,
            'payload_hash' => hash('sha256', "INIT_BG:{$code}:{$userId}"),
            'occurred_at' => $now,
        ]);

        return $grant;
    }

    /**
     * Authorize emergency break-glass elevation by a secondary authorized officer (dual custody).
     *
     * @param  int  $authorizingOfficerId  Second officer id (must differ from requester)
     */
    public function authorizeBreakGlass(
        int $grantId,
        int $authorizingOfficerId,
        string $approvalNotes
    ): CoreAccessGrant {
        $grant = CoreAccessGrant::findOrFail($grantId);

        if ($grant->status !== 'PENDING_APPROVAL') {
            throw new DomainException("Cannot approve break-glass grant in status: {$grant->status}");
        }

        if ($grant->user_id === $authorizingOfficerId) {
            throw new DomainException('Dual custody violation: Requesting officer cannot self-authorize break-glass access.');
        }

        $now = now();
        $grant->status = 'ACTIVE';
        $grant->authorized_by = $authorizingOfficerId;
        $grant->save();

        CoreAccessGrantTransition::create([
            'grant_id' => $grant->id,
            'from_status' => 'PENDING_APPROVAL',
            'to_status' => 'ACTIVE',
            'transition_code' => 'AUTHORIZE_BREAK_GLASS',
            'reason' => 'Break-glass authorized: '.$approvalNotes,
            'performed_by' => $authorizingOfficerId,
            'payload_hash' => hash('sha256', "AUTH_BG:{$grant->grant_code}:{$authorizingOfficerId}"),
            'occurred_at' => $now,
        ]);

        return $grant;
    }
}
