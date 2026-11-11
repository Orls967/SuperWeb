<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Values;

use InvalidArgumentException;

/**
 * Immutable evaluation context representing the requesting subject (W8-D53, W8-D54).
 *
 * Encapsulates multi-dimensional scoping dimensions:
 * - Tenant context (primary isolation boundary)
 * - Organization/Department hierarchy
 * - Security clearance level (1 to 5)
 * - Device risk & trust factors
 * - Session authentication factors (MFA, break-glass)
 */
final class AccessSubjectContext
{
    /**
     * @param  string  $subjectId  Unique identifier of the subject (e.g., user UUID or system service code)
     * @param  string  $tenantId  Primary tenant UUID
     * @param  string  $organizationUnitId  Department or organizational unit identifier
     * @param  list<string>  $roles  List of assigned role codes
     * @param  int  $clearanceLevel  Numeric clearance level (1=Public, 2=Internal, 3=Confidential, 4=Secret, 5=TopSecret)
     * @param  list<string>  $departmentCodes  Ancestor and current department codes for hierarchical checking
     * @param  string  $ipAddress  Client IP address
     * @param  string  $geoCountryIso  ISO 3166-1 alpha-2 country code
     * @param  int  $deviceTrustScore  Trust score from 0 (untrusted) to 100 (fully managed corporate hardware)
     * @param  bool  $isMfaVerified  Whether MFA was verified in current session
     * @param  bool  $isBreakGlassSession  Whether running under emergency elevated break-glass mode
     * @param  int  $sessionRiskScore  Calculated session anomaly score (0 to 100)
     * @param  array<string, mixed>  $customAttributes  Additional key-value attributes for dynamic ABAC evaluation
     */
    public function __construct(
        public readonly string $subjectId,
        public readonly string $tenantId,
        public readonly string $organizationUnitId,
        public readonly array $roles,
        public readonly int $clearanceLevel = 1,
        public readonly array $departmentCodes = [],
        public readonly string $ipAddress = '127.0.0.1',
        public readonly string $geoCountryIso = 'ID',
        public readonly int $deviceTrustScore = 100,
        public readonly bool $isMfaVerified = false,
        public readonly bool $isBreakGlassSession = false,
        public readonly int $sessionRiskScore = 0,
        public readonly array $customAttributes = []
    ) {
        if ($clearanceLevel < 1 || $clearanceLevel > 5) {
            throw new InvalidArgumentException("Clearance level must be between 1 and 5, got: {$clearanceLevel}");
        }

        if ($deviceTrustScore < 0 || $deviceTrustScore > 100) {
            throw new InvalidArgumentException("Device trust score must be between 0 and 100, got: {$deviceTrustScore}");
        }

        if ($sessionRiskScore < 0 || $sessionRiskScore > 100) {
            throw new InvalidArgumentException("Session risk score must be between 0 and 100, got: {$sessionRiskScore}");
        }
    }

    /**
     * Check if the subject has a specific assigned role.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * Check if the subject has at least one role from a given list.
     *
     * @param  list<string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if clearance meets or exceeds the required threshold.
     */
    public function meetsClearance(int $requiredClearance): bool
    {
        return $this->clearanceLevel >= $requiredClearance;
    }

    /**
     * Check if the subject belongs to a specific department or its ancestors.
     */
    public function belongsToDepartment(string $departmentCode): bool
    {
        return $this->organizationUnitId === $departmentCode || in_array($departmentCode, $this->departmentCodes, true);
    }

    /**
     * Retrieve a specific dynamic attribute or default if missing.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->customAttributes[$key] ?? $default;
    }

    /**
     * Export as flat evaluation dictionary for condition matching.
     *
     * @return array<string, mixed>
     */
    public function toEvaluationArray(): array
    {
        return array_merge($this->customAttributes, [
            'subject_id' => $this->subjectId,
            'tenant_id' => $this->tenantId,
            'org_unit_id' => $this->organizationUnitId,
            'roles' => $this->roles,
            'clearance_level' => $this->clearanceLevel,
            'department_codes' => $this->departmentCodes,
            'ip_address' => $this->ipAddress,
            'geo_country' => $this->geoCountryIso,
            'device_trust_score' => $this->deviceTrustScore,
            'is_mfa_verified' => $this->isMfaVerified,
            'is_break_glass' => $this->isBreakGlassSession,
            'session_risk_score' => $this->sessionRiskScore,
        ]);
    }
}
