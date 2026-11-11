<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Audit;

use DateTimeImmutable;
use Modules\Core\Domain\Access\Values\AccessSubjectContext;
use Modules\Core\Domain\Access\Values\ActionDescriptor;
use Modules\Core\Domain\Access\Values\EnvironmentContext;
use Modules\Core\Domain\Access\Values\ResourceDescriptor;

/**
 * Synthetic Access Vector & Chaos Generator (W8-D53, W8-D58).
 *
 * Generates structured, deterministic permutations of ABAC request contexts,
 * boundary probe conditions, and stress-test scenarios for automated validation.
 */
final class SyntheticAccessVectorFactory
{
    /**
     * Generate standard benign user request vector.
     */
    public static function createStandardEmployeeVector(string $tenantId = '0193188a-7800-7000-8000-000000000001'): array
    {
        $subject = new AccessSubjectContext(
            subjectId: 'USR-BENIGN-01',
            tenantId: $tenantId,
            organizationUnitId: 'FINANCE-AP',
            roles: ['finance_staff'],
            clearanceLevel: 2,
            departmentCodes: ['FINANCE-AP', 'FINANCE-CORP'],
            ipAddress: '10.200.1.45',
            geoCountryIso: 'ID',
            deviceTrustScore: 95,
            isMfaVerified: true,
            isBreakGlassSession: false,
            sessionRiskScore: 5
        );

        $resource = new ResourceDescriptor(
            resourceType: 'purchase_order',
            resourceId: 'PO-2026-001',
            ownerTenantId: $tenantId,
            classification: ResourceDescriptor::CLASS_INTERNAL,
            ownerDepartmentId: 'FINANCE-AP',
            requiredClearance: 2,
            containsPii: false,
            isFinancialTranche: false
        );

        $action = new ActionDescriptor(
            actionCode: 'procurement.view_po',
            category: ActionDescriptor::CATEGORY_READ,
            riskTier: ActionDescriptor::RISK_LOW
        );

        $env = new EnvironmentContext(
            timestamp: new DateTimeImmutable,
            isBusinessHours: true,
            isWeekend: false,
            securityZone: 'INTERNAL_LAN',
            threatLevel: 'GREEN'
        );

        return [
            'subject' => $subject,
            'resource' => $resource,
            'action' => $action,
            'environment' => $env,
        ];
    }

    /**
     * Generate malicious / high-risk impossible travel vector.
     */
    public static function createImpossibleTravelAttackVector(string $tenantId = '0193188a-7800-7000-8000-000000000001'): array
    {
        $subject = new AccessSubjectContext(
            subjectId: 'USR-ATTACK-02',
            tenantId: $tenantId,
            organizationUnitId: 'IT-SEC',
            roles: ['admin'],
            clearanceLevel: 5,
            departmentCodes: ['IT-SEC'],
            ipAddress: '185.220.101.5', // Tor exit node / foreign IP
            geoCountryIso: 'RU',
            deviceTrustScore: 10,
            isMfaVerified: false,
            isBreakGlassSession: false,
            sessionRiskScore: 95
        );

        $resource = new ResourceDescriptor(
            resourceType: 'master_encryption_key',
            resourceId: 'KEY-ROOT-001',
            ownerTenantId: $tenantId,
            classification: ResourceDescriptor::CLASS_RESTRICTED,
            ownerDepartmentId: 'IT-SEC',
            requiredClearance: 5,
            containsPii: true,
            isFinancialTranche: true
        );

        $action = new ActionDescriptor(
            actionCode: 'system.export_master_key',
            category: ActionDescriptor::CATEGORY_EXPORT,
            riskTier: ActionDescriptor::RISK_CRITICAL,
            requiresDualCustody: true,
            requiresStepUpMfa: true
        );

        $env = new EnvironmentContext(
            timestamp: new DateTimeImmutable('2026-11-12 03:30:00'),
            isBusinessHours: false,
            isWeekend: true,
            securityZone: 'PUBLIC_INTERNET',
            threatLevel: 'RED'
        );

        return [
            'subject' => $subject,
            'resource' => $resource,
            'action' => $action,
            'environment' => $env,
        ];
    }

    /**
     * Generate cross-tenant leakage test vector.
     */
    public static function createCrossTenantLeakageVector(
        string $attackerTenantId = '0193188a-7800-7000-8000-000000000001',
        string $victimTenantId = '0193188a-7800-7000-8000-000000000002'
    ): array {
        $subject = new AccessSubjectContext(
            subjectId: 'USR-PROBE-03',
            tenantId: $attackerTenantId,
            organizationUnitId: 'PROCUREMENT',
            roles: ['buyer'],
            clearanceLevel: 2
        );

        $resource = new ResourceDescriptor(
            resourceType: 'supplier_invoice',
            resourceId: 'INV-SECRET-999',
            ownerTenantId: $victimTenantId,
            classification: ResourceDescriptor::CLASS_CONFIDENTIAL,
            requiredClearance: 3
        );

        $action = new ActionDescriptor(
            actionCode: 'invoice.view',
            category: ActionDescriptor::CATEGORY_READ,
            riskTier: ActionDescriptor::RISK_MEDIUM
        );

        return [
            'subject' => $subject,
            'resource' => $resource,
            'action' => $action,
            'expected_outcome' => 'DENIED_CROSS_TENANT_LEAKAGE',
        ];
    }
}
