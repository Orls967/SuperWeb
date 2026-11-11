<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Values;

use InvalidArgumentException;

/**
 * Immutable representation of a compiled ABAC policy rule (W8-D53).
 *
 * Rules specify matching conditions and effects (PERMIT or DENY).
 * When evaluated, if conditions evaluate to true, the rule's effect is produced.
 */
final class PolicyRuleDefinition
{
    public const EFFECT_PERMIT = 'PERMIT';

    public const EFFECT_DENY = 'DENY';

    /**
     * @param  string  $ruleCode  Unique rule identifier
     * @param  string  $policyCode  Parent policy code
     * @param  string  $effect  Rule outcome if conditions match (PERMIT or DENY)
     * @param  int  $priority  Evaluation priority (lower numbers evaluate first)
     * @param  array<string, mixed>  $targetCriteria  Target filters (resource types, actions, tenants)
     * @param  array<string, mixed>  $conditions  Structured condition tree (AND, OR, NOT, comparisons)
     * @param  list<string>  $obligations  Mandatory side-effect obligations on match (e.g., 'LOG_AUDIT', 'NOTIFY_DPO')
     * @param  string  $description  Business justification for the rule
     */
    public function __construct(
        public readonly string $ruleCode,
        public readonly string $policyCode,
        public readonly string $effect,
        public readonly int $priority = 100,
        public readonly array $targetCriteria = [],
        public readonly array $conditions = [],
        public readonly array $obligations = [],
        public readonly string $description = ''
    ) {
        if ($effect !== self::EFFECT_PERMIT && $effect !== self::EFFECT_DENY) {
            throw new InvalidArgumentException("Rule effect must be PERMIT or DENY, got: {$effect}");
        }
    }

    /**
     * Check if this rule is a DENY rule.
     */
    public function isDenyRule(): bool
    {
        return $this->effect === self::EFFECT_DENY;
    }

    /**
     * Check if this rule is a PERMIT rule.
     */
    public function isPermitRule(): bool
    {
        return $this->effect === self::EFFECT_PERMIT;
    }

    /**
     * Check if target criteria match the request target.
     *
     * @param  array<string, mixed>  $target
     */
    public function matchesTarget(array $target): bool
    {
        if (empty($this->targetCriteria)) {
            return true;
        }

        foreach ($this->targetCriteria as $key => $expected) {
            $actual = $target[$key] ?? null;
            if ($actual === null) {
                return false;
            }

            if (is_array($expected)) {
                if (! in_array($actual, $expected, true)) {
                    return false;
                }
            } elseif ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }
}
