<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use Illuminate\Support\Str;
use Modules\Core\Domain\Access\Cache\AbacMemoryCache;
use Modules\Core\Domain\Access\Rules\PolicyCompiler;
use Modules\Core\Domain\Models\CoreAccessPolicy;
use Modules\Shared\Domain\Attributes\NotAControl;

/**
 * ABAC Policy Administration & Lifecycle Service (W8-D53).
 *
 * Manages authoring, compilation, semantic validation, activation,
 * and versioned deprecation of attribute-based access control policies.
 */
class AccessPolicyAdministrationService
{
    public function __construct(
        private readonly PolicyCompiler $compiler,
        private readonly AbacMemoryCache $memoryCache
    ) {}

    /**
     * Create and compile a new ABAC policy.
     *
     * @param  string  $effect  PERMIT or DENY
     * @param  array<string, mixed>  $rules
     */
    public function createPolicy(
        string $tenantId,
        string $name,
        string $effect,
        array $rules,
        string $description = '',
        int $priority = 100,
        #[NotAControl('Flag status aktivasi kebijakan akses')] bool $isActive = true
    ): CoreAccessPolicy {
        $policyCode = 'POL-'.strtoupper(Str::random(8));

        // Compile and validate rules
        $compiledRules = $this->compiler->compilePolicy($policyCode, ['rules' => $rules]);

        $policy = CoreAccessPolicy::create([
            'policy_code' => $policyCode,
            'tenant_id' => $tenantId,
            'name' => $name,
            'description' => $description,
            'effect' => strtoupper($effect),
            'target_resource' => $rules[0]['target']['resource_type'] ?? '*',
            'target_action' => $rules[0]['target']['action_code'] ?? '*',
            'rules' => $rules,
            'priority' => $priority,
            'is_active' => $isActive,
        ]);

        // Invalidate tenant ABAC cache
        $this->memoryCache->evictTenant($tenantId);

        return $policy;
    }

    /**
     * Toggle active state of a policy.
     */
    public function togglePolicyStatus(
        int $policyId,
        #[NotAControl('Flag status aktivasi kebijakan akses')] bool $isActive
    ): CoreAccessPolicy {
        $policy = CoreAccessPolicy::findOrFail($policyId);
        $policy->is_active = $isActive;
        $policy->save();

        if ($policy->tenant_id !== null) {
            $this->memoryCache->evictTenant($policy->tenant_id);
        } else {
            $this->memoryCache->flush();
        }

        return $policy;
    }
}
