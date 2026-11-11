<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Rules;

use DomainException;
use Modules\Core\Domain\Access\Attributes\AttributeDictionary;
use Modules\Core\Domain\Access\Values\PolicyRuleDefinition;

/**
 * Policy Rule Compiler & Static Validator (W8-D53).
 *
 * Compiles raw array/JSON policy definitions into strongly-typed PolicyRuleDefinition instances,
 * performing semantic validation against the AttributeDictionary.
 */
final class PolicyCompiler
{
    /**
     * Compile raw policy payload into a list of PolicyRuleDefinition instances.
     *
     * @param  string  $policyCode  Parent policy code
     * @param  array<string, mixed>  $rawPayload  Raw JSON or array representation
     * @return list<PolicyRuleDefinition>
     */
    public function compilePolicy(string $policyCode, array $rawPayload): array
    {
        $rawRules = $rawPayload['rules'] ?? [];
        if (! is_array($rawRules)) {
            throw new DomainException("Policy payload must contain a 'rules' array.");
        }

        $compiled = [];
        foreach ($rawRules as $idx => $rawRule) {
            $ruleCode = (string) ($rawRule['rule_code'] ?? "{$policyCode}_R".($idx + 1));
            $effect = strtoupper((string) ($rawRule['effect'] ?? 'DENY'));
            $priority = (int) ($rawRule['priority'] ?? 100);
            $target = (array) ($rawRule['target'] ?? []);
            $conditions = (array) ($rawRule['conditions'] ?? []);
            $obligations = (array) ($rawRule['obligations'] ?? []);
            $description = (string) ($rawRule['description'] ?? '');

            $this->validateRuleSemantics($ruleCode, $target, $conditions);

            $compiled[] = new PolicyRuleDefinition(
                ruleCode: $ruleCode,
                policyCode: $policyCode,
                effect: $effect,
                priority: $priority,
                targetCriteria: $target,
                conditions: $conditions,
                obligations: $obligations,
                description: $description
            );
        }

        // Sort rules by priority ascending (lower number = higher priority)
        usort($compiled, fn (PolicyRuleDefinition $a, PolicyRuleDefinition $b) => $a->priority <=> $b->priority);

        return $compiled;
    }

    /**
     * Semantically validate condition structure and referenced attributes.
     *
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $conditions
     */
    private function validateRuleSemantics(string $ruleCode, array $target, array $conditions): void
    {
        // Recursively inspect condition node
        $this->inspectConditionNode($ruleCode, $conditions);
    }

    /**
     * Inspect a single condition node.
     *
     * @param  array<string, mixed>  $node
     */
    private function inspectConditionNode(string $ruleCode, array $node): void
    {
        if (empty($node)) {
            return;
        }

        if (isset($node['AND']) && is_array($node['AND'])) {
            foreach ($node['AND'] as $child) {
                if (is_array($child)) {
                    $this->inspectConditionNode($ruleCode, $child);
                }
            }

            return;
        }

        if (isset($node['OR']) && is_array($node['OR'])) {
            foreach ($node['OR'] as $child) {
                if (is_array($child)) {
                    $this->inspectConditionNode($ruleCode, $child);
                }
            }

            return;
        }

        if (isset($node['NOT']) && is_array($node['NOT'])) {
            $this->inspectConditionNode($ruleCode, $node['NOT']);

            return;
        }

        // Validate atomic attribute
        if (isset($node['attribute'])) {
            $attr = (string) $node['attribute'];
            if (AttributeDictionary::has($attr) && isset($node['value'])) {
                AttributeDictionary::validate($attr, $node['value']);
            }
        }
    }
}
