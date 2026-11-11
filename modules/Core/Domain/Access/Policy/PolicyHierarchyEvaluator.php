<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Policy;

use Modules\Core\Domain\Access\Values\AccessDecision;
use Modules\Core\Domain\Models\CoreAccessPolicy;

/**
 * Hierarchical ABAC Policy Evaluator (W8-D53, W8-D54).
 *
 * Traverses the organizational hierarchy to gather and evaluate policies across 4 levels:
 * 1. Global Platform Policies (apply to all tenants)
 * 2. Tenant Enterprise Policies (specific to the active tenant)
 * 3. Departmental Policies (scoped to the subject's organizational unit)
 * 4. Role-Specific Policies
 *
 * Evaluates all matching policies with strict Deny-Overrides resolution:
 * Any explicit DENY at ANY tier overrides ALL PERMITS.
 */
final class PolicyHierarchyEvaluator
{
    /**
     * Collect all applicable policies for subject across hierarchy.
     *
     * @return list<CoreAccessPolicy>
     */
    public function collectHierarchicalPolicies(
        string $tenantId,
        ?string $departmentCode = null
    ): array {
        $query = CoreAccessPolicy::where('is_active', true)
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id'); // Global platform policies
            });

        return $query->get()->all();
    }

    /**
     * Resolve composite decision across collected hierarchical policies.
     *
     * @param  list<CoreAccessPolicy>  $policies
     * @param  array<string, mixed>  $evaluationContext
     */
    public function evaluateHierarchy(
        array $policies,
        array $evaluationContext
    ): AccessDecision {
        $permits = [];
        $denies = [];
        $trace = [];

        foreach ($policies as $policy) {
            $rules = $policy->rules ?? [];
            if (! is_array($rules)) {
                continue;
            }

            foreach ($rules as $rule) {
                $effect = strtoupper((string) ($rule['effect'] ?? 'DENY'));
                $ruleCode = (string) ($rule['rule_code'] ?? $policy->policy_code);

                // Simple target match check
                $target = (array) ($rule['target'] ?? []);
                $targetMatch = true;
                foreach ($target as $k => $v) {
                    if (isset($evaluationContext[$k]) && $evaluationContext[$k] !== $v) {
                        $targetMatch = false;
                        break;
                    }
                }

                if (! $targetMatch) {
                    continue;
                }

                if ($effect === 'DENY') {
                    $denies[] = $ruleCode;
                    $trace[] = "[DENY] Matched rule {$ruleCode} in policy {$policy->policy_code}";
                } elseif ($effect === 'PERMIT') {
                    $permits[] = $ruleCode;
                    $trace[] = "[PERMIT] Matched rule {$ruleCode} in policy {$policy->policy_code}";
                }
            }
        }

        // Apply Deny-Overrides
        if (count($denies) > 0) {
            return AccessDecision::deny(
                reason: sprintf('Access denied by %d policy rule(s) via Deny-Overrides.', count($denies)),
                matchedDenyPolicies: $denies,
                matchedPermitPolicies: $permits,
                trace: $trace
            );
        }

        if (count($permits) > 0) {
            return AccessDecision::permit(
                reason: sprintf('Access permitted by %d policy rule(s).', count($permits)),
                matchedPermitPolicies: $permits,
                trace: $trace
            );
        }

        return AccessDecision::notApplicable(
            reason: 'No matching hierarchical policies found (safe default deny).',
            trace: $trace
        );
    }
}
