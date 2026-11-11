<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Resolvers;

use App\Models\User;
use Modules\Core\Domain\Access\Values\AccessSubjectContext;

/**
 * Subject Attribute Resolver (W8-D53, W8-D54).
 *
 * Extracts and enriches identity attributes from User model, session, and tenant metadata.
 */
final class SubjectAttributeResolver
{
    /**
     * Resolve comprehensive AccessSubjectContext from User model and HTTP request factors.
     *
     * @param  User  $user  Authenticated user
     * @param  string|null  $tenantId  Active tenant override or user default
     * @param  string  $ipAddress  Client IP
     * @param  array<string, mixed>  $sessionAttributes  Additional session signals
     */
    public function resolveFromUser(
        User $user,
        ?string $tenantId = null,
        string $ipAddress = '127.0.0.1',
        array $sessionAttributes = []
    ): AccessSubjectContext {
        $resolvedTenantId = $tenantId ?? (string) ($user->tenant_id ?? '0193188a-7800-7000-8000-000000000001');

        $roles = [];
        if (isset($user->roles) && is_iterable($user->roles)) {
            foreach ($user->roles as $role) {
                $roles[] = is_string($role) ? $role : (string) ($role->name ?? $role->slug ?? 'user');
            }
        } elseif (isset($user->role) && is_string($user->role)) {
            $roles[] = $user->role;
        }

        if (empty($roles)) {
            $roles[] = 'user';
        }

        $clearance = (int) ($user->clearance_level ?? 1);
        if ($clearance < 1 || $clearance > 5) {
            $clearance = in_array('admin', $roles, true) ? 4 : 2;
        }

        $orgUnit = (string) ($user->department ?? $user->organization_unit_id ?? 'CORP-HQ');
        $deptCodes = isset($user->department_hierarchy) && is_array($user->department_hierarchy)
            ? $user->department_hierarchy
            : [$orgUnit];

        $isMfa = (bool) ($sessionAttributes['mfa_verified'] ?? ($user->two_factor_confirmed_at !== null));
        $isBreakGlass = (bool) ($sessionAttributes['break_glass'] ?? false);
        $deviceTrust = (int) ($sessionAttributes['device_trust_score'] ?? 85);
        $riskScore = (int) ($sessionAttributes['session_risk_score'] ?? 10);
        $country = (string) ($sessionAttributes['country_iso'] ?? 'ID');

        return new AccessSubjectContext(
            subjectId: (string) $user->id,
            tenantId: $resolvedTenantId,
            organizationUnitId: $orgUnit,
            roles: $roles,
            clearanceLevel: $clearance,
            departmentCodes: $deptCodes,
            ipAddress: $ipAddress,
            geoCountryIso: $country,
            deviceTrustScore: $deviceTrust,
            isMfaVerified: $isMfa,
            isBreakGlassSession: $isBreakGlass,
            sessionRiskScore: $riskScore,
            customAttributes: [
                'email' => (string) ($user->email ?? ''),
                'name' => (string) ($user->name ?? ''),
                'is_staff' => (bool) ($user->is_staff ?? false),
                'locale' => (string) ($user->locale ?? 'id_ID'),
            ]
        );
    }
}
