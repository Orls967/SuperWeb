<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\Models\CoreAccessPolicy;
use Modules\Core\Domain\Models\CoreIdentityLink;
use Modules\Core\Domain\Models\CoreVendorExposureSnapshot;

/**
 * Access Governance & Regulatory Export Service (W8-D53, W8-D60).
 *
 * Produces structured compliance audit packages for external regulators and auditors
 * (SOC 2, ISO 27001, OJK compliance).
 */
class AccessGovernanceExportService
{
    /**
     * Generate comprehensive audit snapshot in JSON format.
     *
     * @return array<string, mixed>
     */
    public function generateAuditPackage(?string $tenantId = null): array
    {
        $grantQuery = CoreAccessGrant::query();
        $policyQuery = CoreAccessPolicy::query();
        $identityQuery = CoreIdentityLink::query();
        $vendorQuery = CoreVendorExposureSnapshot::query();

        if ($tenantId !== null) {
            $grantQuery->where('tenant_id', $tenantId);
            $policyQuery->where('tenant_id', $tenantId);
            $identityQuery->where('tenant_id', $tenantId);
        }

        $grants = $grantQuery->with('transitions')->get();
        $policies = $policyQuery->get();
        $identities = $identityQuery->get();
        $vendorSnapshots = $vendorQuery->latest('id')->limit(20)->get();

        return [
            'export_metadata' => [
                'generated_at' => now()->toIso8601String(),
                'tenant_id' => $tenantId ?? 'ALL_TENANTS',
                'compliance_standards' => ['SOC_2_TYPE_II', 'ISO_27001:2022', 'OJK_POJK_38'],
                'package_version' => '1.0.0',
            ],
            'summary_metrics' => [
                'total_policies' => $policies->count(),
                'total_grants' => $grants->count(),
                'active_grants' => $grants->where('status', 'ACTIVE')->count(),
                'revoked_grants' => $grants->where('status', 'REVOKED')->count(),
                'total_identity_links' => $identities->count(),
                'active_identity_links' => $identities->where('is_active', true)->count(),
                'vendor_clusters_monitored' => $vendorSnapshots->count(),
            ],
            'policies' => $policies->map(fn ($p) => [
                'policy_code' => $p->policy_code,
                'name' => $p->name,
                'effect' => $p->effect,
                'is_active' => $p->is_active,
            ])->all(),
            'grants' => $grants->map(fn ($g) => [
                'grant_code' => $g->grant_code,
                'user_id' => $g->user_id,
                'role_or_permission' => $g->role_or_permission,
                'status' => $g->status,
                'transition_count' => $g->transitions->count(),
                'expires_at' => $g->expires_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
