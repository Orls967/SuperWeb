<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IdentityTenancyService (Fase 188)
 *
 * Implements:
 *  - 188.1 Centralized RBAC + ABAC policy evaluator (role + tenant/region scope)
 *  - 188.2 Customer identity graph linking with strict consent gating & revocation
 *  - 188.3 Consolidated vendor credit exposure across 30 lines
 */
class IdentityTenancyService
{
    /**
     * Define RBAC + ABAC access policy.
     */
    public function definePolicy(string $role, string $permission, string $tenantScope, string $regionScope): object
    {
        $code = 'POL-IDP-'.strtoupper(Str::random(8));

        $id = DB::table('idp_access_policies')->insertGetId([
            'policy_code' => $code,
            'role_code' => strtoupper($role),
            'permission' => strtoupper($permission),
            'allowed_tenant_scope' => strtoupper($tenantScope),
            'allowed_region_scope' => strtoupper($regionScope),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('idp_access_policies')->find($id);
    }

    /**
     * Evaluate access request using unified RBAC + ABAC rules.
     */
    public function authorizeRequest(string $role, string $permission, string $requestedTenant, string $requestedRegion): bool
    {
        $policy = DB::table('idp_access_policies')
            ->where('role_code', strtoupper($role))
            ->where('permission', strtoupper($permission))
            ->where('is_active', true)
            ->first();

        if (! $policy) {
            return false;
        }

        // Scope validation
        if ($policy->allowed_tenant_scope !== 'ALL' && $policy->allowed_tenant_scope !== strtoupper($requestedTenant)) {
            return false;
        }

        if ($policy->allowed_region_scope !== 'ALL' && $policy->allowed_region_scope !== strtoupper($requestedRegion)) {
            return false;
        }

        return true;
    }

    /**
     * Link customer across verticals with consent.
     */
    public function linkCustomerConsent(int $customerId, string $sourceVertical, string $targetVertical, bool $consentGranted): object
    {
        $code = 'LNK-IDP-'.strtoupper(Str::random(8));

        $id = DB::table('idp_customer_identity_links')->insertGetId([
            'link_code' => $code,
            'customer_id' => $customerId,
            'source_vertical' => strtoupper($sourceVertical),
            'target_vertical' => strtoupper($targetVertical),
            'consent_granted' => $consentGranted,
            'consent_revoked_at' => $consentGranted ? null : now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('idp_customer_identity_links')->find($id);
    }

    /**
     * Revoke customer consent instantly.
     */
    public function revokeCustomerConsent(string $linkCode): object
    {
        DB::table('idp_customer_identity_links')->where('link_code', $linkCode)->update([
            'consent_granted' => false,
            'consent_revoked_at' => Carbon::now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('idp_customer_identity_links')->where('link_code', $linkCode)->first();
    }

    /**
     * Record vendor cross-line credit exposure and enforce group limit.
     */
    public function recordVendorExposure(string $vendorCode, float $groupCreditLimit, float $newExposureIncrement): object
    {
        $existing = DB::table('idp_vendor_group_exposures')->where('vendor_code', $vendorCode)->first();

        if (! $existing) {
            $totalExposure = round($newExposureIncrement, 2);
            $breached = ($totalExposure > $groupCreditLimit);

            $id = DB::table('idp_vendor_group_exposures')->insertGetId([
                'vendor_code' => $vendorCode,
                'group_credit_limit_idr' => $groupCreditLimit,
                'current_consolidated_exposure_idr' => $totalExposure,
                'is_limit_breached' => $breached,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (object) DB::table('idp_vendor_group_exposures')->find($id);
        }

        $totalExposure = round((float) $existing->current_consolidated_exposure_idr + $newExposureIncrement, 2);
        $breached = ($totalExposure > $groupCreditLimit);

        DB::table('idp_vendor_group_exposures')->where('vendor_code', $vendorCode)->update([
            'current_consolidated_exposure_idr' => $totalExposure,
            'is_limit_breached' => $breached,
            'updated_at' => now(),
        ]);

        return (object) DB::table('idp_vendor_group_exposures')->where('vendor_code', $vendorCode)->first();
    }

    /**
     * Quality audit gate (`securitytenancy:audit`).
     */
    public function audit(): array
    {
        // Unconsented active links
        $unconsentedActiveLinks = DB::table('idp_customer_identity_links')
            ->where('consent_granted', false)
            ->whereNull('consent_revoked_at')
            ->count();

        return [
            'status' => $unconsentedActiveLinks === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_policies' => DB::table('idp_access_policies')->count(),
            'total_customer_links' => DB::table('idp_customer_identity_links')->count(),
            'total_vendor_exposures' => DB::table('idp_vendor_group_exposures')->count(),
            'discrepancy_count' => $unconsentedActiveLinks,
        ];
    }
}
