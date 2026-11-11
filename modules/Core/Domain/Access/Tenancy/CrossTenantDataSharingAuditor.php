<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Tenancy;

use DateTimeImmutable;

/**
 * Cross-Tenant Data Transfer & Consortium Sharing Auditor (W8-D54, W8-D58).
 *
 * Performs runtime auditing of cross-tenant queries and data exchanges to prevent
 * unauthorized multi-tenancy bypass and verify compliance with consortium agreements.
 */
final class CrossTenantDataSharingAuditor
{
    public function __construct(
        private readonly TenantDataIsolationPolicy $policy
    ) {}

    /**
     * Audit a data exchange event between two tenants.
     *
     * @param  list<string>  $transferredEntityIds
     * @return array{
     *     timestamp: string,
     *     is_authorized: bool,
     *     violation_severity: string,
     *     audit_message: string,
     *     entity_count: int,
     *     compliance_reference: string
     * }
     */
    public function auditDataExchange(
        string $requestingTenantId,
        string $sourceTenantId,
        string $dataDomain,
        array $transferredEntityIds
    ): array {
        $now = new DateTimeImmutable;
        $isAuthorized = $this->policy->canAccessCrossTenantData(
            requestingTenantId: $requestingTenantId,
            resourceOwnerTenantId: $sourceTenantId,
            dataDomain: $dataDomain
        );

        $severity = match (true) {
            $isAuthorized => 'INFO',
            $dataDomain === 'PRIVATE_FINANCIAL' => 'CRITICAL',
            $dataDomain === 'PII_HEALTHCARE' => 'CRITICAL',
            default => 'HIGH',
        };

        $message = $isAuthorized
            ? "Legitimate cross-tenant data exchange authorized under consortium agreement ({$sourceTenantId} -> {$requestingTenantId})."
            : "Unauthorized cross-tenant data access attempt detected from tenant [{$requestingTenantId}] to [{$sourceTenantId}] on domain [{$dataDomain}].";

        return [
            'timestamp' => $now->format('c'),
            'is_authorized' => $isAuthorized,
            'violation_severity' => $severity,
            'audit_message' => $message,
            'entity_count' => count($transferredEntityIds),
            'compliance_reference' => 'POJK-38/2021-SEC-TENANCY',
        ];
    }
}
