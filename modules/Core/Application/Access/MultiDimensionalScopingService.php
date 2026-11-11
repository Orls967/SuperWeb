<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use App\Models\User;
use Modules\Core\Domain\Access\Resolvers\EnvironmentAttributeResolver;
use Modules\Core\Domain\Access\Resolvers\SubjectAttributeResolver;

/**
 * Multi-Dimensional Scoping Service (W8-D54).
 *
 * Computes composite 4-dimensional access scope vectors:
 * [Tenant × Organization × Role × Environment Context]
 * to apply context-aware authorization constraints across API and web requests.
 */
class MultiDimensionalScopingService
{
    public function __construct(
        private readonly SubjectAttributeResolver $subjectResolver,
        private readonly EnvironmentAttributeResolver $environmentResolver
    ) {}

    /**
     * Compute full composite evaluation scope.
     *
     * @param  User  $user  Current user
     * @param  string|null  $tenantId  Target tenant
     * @param  string  $clientIp  Request IP
     * @param  array<string, mixed>  $extraSignals
     */
    public function computeCompositeScope(
        User $user,
        ?string $tenantId = null,
        string $clientIp = '127.0.0.1',
        array $extraSignals = []
    ): array {
        $subject = $this->subjectResolver->resolveFromUser(
            user: $user,
            tenantId: $tenantId,
            ipAddress: $clientIp,
            sessionAttributes: $extraSignals
        );

        $environment = $this->environmentResolver->resolve(
            clientIp: $clientIp,
            isEmergencyWindow: (bool) ($extraSignals['is_emergency'] ?? false)
        );

        $vectorHash = hash('sha256', implode(':', [
            $subject->tenantId,
            $subject->organizationUnitId,
            implode(',', $subject->roles),
            $environment->securityZone,
            $environment->threatLevel,
        ]));

        return [
            'tenant_id' => $subject->tenantId,
            'org_unit_id' => $subject->organizationUnitId,
            'roles' => $subject->roles,
            'clearance_level' => $subject->clearanceLevel,
            'is_mfa_verified' => $subject->isMfaVerified,
            'is_break_glass' => $subject->isBreakGlassSession,
            'security_zone' => $environment->securityZone,
            'is_business_hours' => $environment->isBusinessHours,
            'scope_vector_hash' => $vectorHash,
            'subject_context' => $subject,
            'environment_context' => $environment,
        ];
    }
}
