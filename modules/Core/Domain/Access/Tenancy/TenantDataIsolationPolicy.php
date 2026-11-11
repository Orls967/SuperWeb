<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Tenancy;

/**
 * Tenant Data Sharing & Isolation Boundary Policy (W8-D54, W8-D58).
 *
 * Defines explicit consortium sharing agreements, joint venture permissions,
 * and cross-tenant federated read rules while preventing unauthorized data leaks.
 */
final class TenantDataIsolationPolicy
{
    /**
     * @var array<string, list<string>> Map of tenant_id => list of authorized shared tenant_ids (consortiums)
     */
    private array $consortiumAgreements = [];

    /**
     * Register a cross-tenant sharing consortium agreement.
     *
     * @param  string  $sourceTenantId  Tenant granting access
     * @param  string  $targetTenantId  Tenant receiving access
     */
    public function registerConsortiumShare(string $sourceTenantId, string $targetTenantId): void
    {
        if ($sourceTenantId === $targetTenantId) {
            return;
        }

        if (! isset($this->consortiumAgreements[$sourceTenantId])) {
            $this->consortiumAgreements[$sourceTenantId] = [];
        }

        if (! in_array($targetTenantId, $this->consortiumAgreements[$sourceTenantId], true)) {
            $this->consortiumAgreements[$sourceTenantId][] = $targetTenantId;
        }
    }

    /**
     * Check if a tenant is authorized to read records owned by another tenant.
     *
     * @param  string  $requestingTenantId  The tenant making the query
     * @param  string  $resourceOwnerTenantId  The tenant that owns the data
     * @param  string  $dataDomain  Domain classification (e.g., 'PUBLIC_CATALOG', 'PRIVATE_FINANCIAL')
     */
    public function canAccessCrossTenantData(
        string $requestingTenantId,
        string $resourceOwnerTenantId,
        string $dataDomain = 'PRIVATE'
    ): bool {
        // 1. Same tenant: always allowed
        if ($requestingTenantId === $resourceOwnerTenantId) {
            return true;
        }

        // 2. Public catalog or global reference data: universally accessible
        if (in_array(strtoupper($dataDomain), ['PUBLIC_CATALOG', 'GLOBAL_REFERENCE', 'SYSTEM_LOOKUP'], true)) {
            return true;
        }

        // 3. Check consortium sharing agreement
        $allowedPartners = $this->consortiumAgreements[$resourceOwnerTenantId] ?? [];

        return in_array($requestingTenantId, $allowedPartners, true);
    }

    /**
     * Revoke a consortium sharing agreement.
     */
    public function revokeConsortiumShare(string $sourceTenantId, string $targetTenantId): bool
    {
        if (! isset($this->consortiumAgreements[$sourceTenantId])) {
            return false;
        }

        $idx = array_search($targetTenantId, $this->consortiumAgreements[$sourceTenantId], true);
        if ($idx !== false) {
            unset($this->consortiumAgreements[$sourceTenantId][$idx]);
            $this->consortiumAgreements[$sourceTenantId] = array_values($this->consortiumAgreements[$sourceTenantId]);

            return true;
        }

        return false;
    }
}
