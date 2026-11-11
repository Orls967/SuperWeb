<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Access;

use InvalidArgumentException;

/**
 * Pure in-memory ABAC policy evaluator using strict Deny-Overrides strategy (W8-D53, C1).
 *
 * Evaluation rules:
 * 1. If at least ONE matched rule has effect == 'DENY' -> DENY immediately.
 * 2. If NO 'DENY' rule matches, and at least ONE matched rule has effect == 'ALLOW' -> ALLOW.
 * 3. Base case (no matching rules) -> DENY (fail-closed default).
 */
class DenyOverridesEvaluator
{
    /**
     * Evaluate rules in memory against context dimensions.
     *
     * @param  list<array<string, mixed>>  $rules
     * @param  array<string, mixed>  $contextDimensions
     * @return array{allowed: bool, decision: 'ALLOW'|'DENY'|'DENY_DEFAULT', matched_rule_id: ?string, certificate: array<string, mixed>}
     */
    public function evaluate(
        array $rules,
        string $resource,
        string $action,
        array $contextDimensions,
        int $nowTimestamp
    ): array {
        if ($nowTimestamp <= 0) {
            throw new InvalidArgumentException('Timestamp evaluasi tidak valid.');
        }

        $matchedAllowRuleId = null;
        $matchedDenyRuleId = null;
        $evaluatedRuleIds = [];

        foreach ($rules as $rule) {
            $ruleId = (string) ($rule['id'] ?? $rule['policy_code'] ?? 'anonymous-rule');
            $ruleResource = (string) ($rule['target_resource'] ?? $rule['resource'] ?? '*');
            $ruleAction = (string) ($rule['action'] ?? '*');
            $ruleEffect = strtoupper((string) ($rule['effect'] ?? 'DENY'));
            $ruleDimensions = (array) ($rule['dimensions'] ?? []);

            // Resource match
            if ($ruleResource !== '*' && $ruleResource !== $resource) {
                continue;
            }

            // Action match
            if ($ruleAction !== '*' && $ruleAction !== $action) {
                continue;
            }

            // Dimensions match
            if (! $this->matchesDimensions($ruleDimensions, $contextDimensions, $nowTimestamp)) {
                continue;
            }

            $evaluatedRuleIds[] = $ruleId;

            if ($ruleEffect === 'DENY') {
                $matchedDenyRuleId = $ruleId;
                // Deny-overrides: One DENY is sufficient to terminate and deny
                break;
            }

            if ($ruleEffect === 'ALLOW' && $matchedAllowRuleId === null) {
                $matchedAllowRuleId = $ruleId;
            }
        }

        if ($matchedDenyRuleId !== null) {
            $decision = 'DENY';
            $allowed = false;
            $winningRuleId = $matchedDenyRuleId;
        } elseif ($matchedAllowRuleId !== null) {
            $decision = 'ALLOW';
            $allowed = true;
            $winningRuleId = $matchedAllowRuleId;
        } else {
            $decision = 'DENY_DEFAULT';
            $allowed = false;
            $winningRuleId = null;
        }

        $certificate = $this->generateCertificate(
            resource: $resource,
            action: $action,
            decision: $decision,
            winningRuleId: $winningRuleId,
            evaluatedRuleIds: $evaluatedRuleIds,
            contextDimensions: $contextDimensions,
            timestamp: $nowTimestamp
        );

        return [
            'allowed' => $allowed,
            'decision' => $decision,
            'matched_rule_id' => $winningRuleId,
            'certificate' => $certificate,
        ];
    }

    /**
     * Check if context dimensions satisfy rule dimensions.
     *
     * @param  array<string, mixed>  $ruleDimensions
     * @param  array<string, mixed>  $contextDimensions
     */
    private function matchesDimensions(array $ruleDimensions, array $contextDimensions, int $nowTimestamp): bool
    {
        // Check temporal window if present in rule
        if (isset($ruleDimensions['valid_from'])) {
            $validFrom = is_numeric($ruleDimensions['valid_from'])
                ? (int) $ruleDimensions['valid_from']
                : (int) strtotime((string) $ruleDimensions['valid_from']);
            if ($nowTimestamp < $validFrom) {
                return false;
            }
        }

        if (isset($ruleDimensions['valid_to'])) {
            $validTo = is_numeric($ruleDimensions['valid_to'])
                ? (int) $ruleDimensions['valid_to']
                : (int) strtotime((string) $ruleDimensions['valid_to']);
            if ($nowTimestamp > $validTo) {
                return false;
            }
        }

        foreach ($ruleDimensions as $dimKey => $expectedValue) {
            if ($dimKey === 'valid_from' || $dimKey === 'valid_to') {
                continue;
            }

            // Wildcard accepts anything
            if ($expectedValue === '*' || $expectedValue === 'ALL' || $expectedValue === ['*']) {
                continue;
            }

            $contextVal = $contextDimensions[$dimKey] ?? null;
            if ($contextVal === null) {
                return false;
            }

            if (is_array($expectedValue)) {
                if (! in_array($contextVal, $expectedValue, true)) {
                    return false;
                }
            } else {
                if ((string) $contextVal !== (string) $expectedValue) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Generate immutable SHA-256 certificate for forensic verification.
     *
     * @param  list<string>  $evaluatedRuleIds
     * @param  array<string, mixed>  $contextDimensions
     * @return array<string, mixed>
     */
    private function generateCertificate(
        string $resource,
        string $action,
        string $decision,
        ?string $winningRuleId,
        array $evaluatedRuleIds,
        array $contextDimensions,
        int $timestamp
    ): array {
        ksort($contextDimensions);
        $payload = [
            'algorithm' => 'DENY_OVERRIDES_ABAC_V1',
            'resource' => $resource,
            'action' => $action,
            'decision' => $decision,
            'winning_rule_id' => $winningRuleId,
            'evaluated_rule_ids' => $evaluatedRuleIds,
            'context_dimensions' => $contextDimensions,
            'timestamp' => $timestamp,
        ];

        $canonicalJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payload['sha256'] = hash('sha256', $canonicalJson);

        return $payload;
    }
}
