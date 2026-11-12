<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Core\Domain\Algorithms\Access\DenyOverridesEvaluator;
use Modules\Core\Domain\Models\CoreAccessPolicy;

/**
 * ABAC Policy Management & In-Memory Evaluation Service (W8-D53, W8-D54).
 */
class AbacPolicyService
{
    public function __construct(
        private readonly DenyOverridesEvaluator $evaluator
    ) {}

    /**
     * Create a new ABAC policy.
     *
     * @param array{
     *     policy_code?: string,
     *     name: string,
     *     target_resource: string,
     *     effect?: 'ALLOW'|'DENY',
     *     dimensions?: array<string, mixed>,
     *     is_active?: bool
     * } $data
     */
    public function createPolicy(array $data): CoreAccessPolicy
    {
        $code = $data['policy_code'] ?? 'POL-'.strtoupper(Str::random(10));

        return CoreAccessPolicy::create([
            'policy_code' => $code,
            'name' => $data['name'],
            'target_resource' => $data['target_resource'],
            'effect' => strtoupper((string) ($data['effect'] ?? 'ALLOW')),
            'dimensions' => $data['dimensions'] ?? [],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    /**
     * Evaluate access in memory for a given user and context dimensions.
     *
     * @param  array<string, mixed>  $contextDimensions
     * @return array{allowed: bool, decision: 'ALLOW'|'DENY'|'DENY_DEFAULT', matched_rule_id: ?string, certificate: array<string, mixed>}
     */
    public function evaluateAccess(
        User $user,
        string $resource,
        string $action,
        array $contextDimensions,
        ?int $nowTimestamp = null
    ): array {
        $now = $nowTimestamp ?? time();

        // Fetch active policies for this resource or wildcard
        $policies = CoreAccessPolicy::where('is_active', true)
            ->where(function ($q) use ($resource): void {
                $q->where('target_resource', $resource)
                    ->orWhere('target_resource', '*');
            })
            ->get();

        $rules = [];
        foreach ($policies as $policy) {
            $rules[] = [
                'id' => $policy->policy_code,
                'target_resource' => $policy->target_resource,
                'action' => '*',
                'effect' => $policy->effect,
                'dimensions' => $policy->dimensions ?? [],
            ];
        }

        return $this->evaluator->evaluate(
            rules: $rules,
            resource: $resource,
            action: $action,
            contextDimensions: $contextDimensions,
            nowTimestamp: $now
        );
    }
}
