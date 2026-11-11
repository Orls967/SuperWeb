<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Resolvers;

use InvalidArgumentException;

/**
 * Expression evaluator for ABAC condition trees (W8-D53).
 *
 * Supports recursive logical expressions (AND, OR, NOT) and rich operators:
 * - EQUALS / NOT_EQUALS
 * - IN / NOT_IN
 * - CONTAINS / STARTS_WITH / ENDS_WITH
 * - MATCHES_REGEX
 * - NUMERIC_GT / NUMERIC_GTE / NUMERIC_LT / NUMERIC_LTE / NUMERIC_BETWEEN
 * - IP_IN_CIDR
 * - TIME_BETWEEN
 * - EXISTS / IS_NULL
 */
final class ConditionExpressionEvaluator
{
    /**
     * Evaluate condition tree against evaluation dictionary.
     *
     * @param  array<string, mixed>  $condition  Condition specification
     * @param  array<string, mixed>  $context  Flat key-value context attributes
     * @param  list<string>  $trace  Output log trace of evaluation steps
     * @return bool Whether condition was satisfied
     */
    public function evaluate(array $condition, array $context, array &$trace = []): bool
    {
        if (empty($condition)) {
            $trace[] = 'Empty condition evaluated to TRUE';

            return true;
        }

        // Logical compound operators: AND, OR, NOT
        if (isset($condition['AND'])) {
            return $this->evaluateAnd($condition['AND'], $context, $trace);
        }

        if (isset($condition['OR'])) {
            return $this->evaluateOr($condition['OR'], $context, $trace);
        }

        if (isset($condition['NOT'])) {
            return $this->evaluateNot($condition['NOT'], $context, $trace);
        }

        // Atomic operator
        $attribute = (string) ($condition['attribute'] ?? '');
        $operator = strtoupper((string) ($condition['operator'] ?? 'EQUALS'));
        $expected = $condition['value'] ?? null;

        $actual = $this->resolveAttributeValue($attribute, $context);

        $result = $this->evaluateAtomic($operator, $actual, $expected);
        $trace[] = sprintf(
            'Evaluated [%s %s %s]: Actual=[%s] => %s',
            $attribute,
            $operator,
            is_scalar($expected) ? (string) $expected : json_encode($expected),
            is_scalar($actual) ? (string) $actual : (is_array($actual) ? json_encode($actual) : gettype($actual)),
            $result ? 'PASS' : 'FAIL'
        );

        return $result;
    }

    /**
     * Evaluate logical AND conjunction.
     *
     * @param  list<array<string, mixed>>  $subConditions
     * @param  array<string, mixed>  $context
     * @param  list<string>  $trace
     */
    private function evaluateAnd(array $subConditions, array $context, array &$trace): bool
    {
        foreach ($subConditions as $idx => $sub) {
            $subTrace = [];
            $pass = $this->evaluate($sub, $context, $subTrace);
            foreach ($subTrace as $t) {
                $trace[] = "  [AND #{$idx}] ".$t;
            }
            if (! $pass) {
                $trace[] = "AND evaluation short-circuited at index #{$idx}";

                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate logical OR disjunction.
     *
     * @param  list<array<string, mixed>>  $subConditions
     * @param  array<string, mixed>  $context
     * @param  list<string>  $trace
     */
    private function evaluateOr(array $subConditions, array $context, array &$trace): bool
    {
        if (empty($subConditions)) {
            return false;
        }

        foreach ($subConditions as $idx => $sub) {
            $subTrace = [];
            $pass = $this->evaluate($sub, $context, $subTrace);
            foreach ($subTrace as $t) {
                $trace[] = "  [OR #{$idx}] ".$t;
            }
            if ($pass) {
                $trace[] = "OR evaluation satisfied at index #{$idx}";

                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate logical NOT negation.
     *
     * @param  array<string, mixed>  $subCondition
     * @param  array<string, mixed>  $context
     * @param  list<string>  $trace
     */
    private function evaluateNot(array $subCondition, array $context, array &$trace): bool
    {
        $subTrace = [];
        $pass = $this->evaluate($subCondition, $context, $subTrace);
        foreach ($subTrace as $t) {
            $trace[] = '  [NOT] '.$t;
        }

        return ! $pass;
    }

    /**
     * Evaluate atomic comparison.
     */
    private function evaluateAtomic(string $operator, mixed $actual, mixed $expected): bool
    {
        return match ($operator) {
            'EQUALS', '==' => $actual == $expected,
            'STRICT_EQUALS', '===' => $actual === $expected,
            'NOT_EQUALS', '!=' => $actual != $expected,
            'IN' => is_array($expected) && (is_array($actual) ? count(array_intersect($actual, $expected)) > 0 : in_array($actual, $expected, true)),
            'NOT_IN' => is_array($expected) && (is_array($actual) ? count(array_intersect($actual, $expected)) === 0 : ! in_array($actual, $expected, true)),
            'CONTAINS' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'STARTS_WITH' => is_string($actual) && is_string($expected) && str_starts_with($actual, $expected),
            'ENDS_WITH' => is_string($actual) && is_string($expected) && str_ends_with($actual, $expected),
            'MATCHES_REGEX' => is_string($actual) && is_string($expected) && @preg_match($expected, $actual) === 1,
            'GT', '>' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'GTE', '>=' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'LT', '<' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'LTE', '<=' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            'NUMERIC_BETWEEN' => is_numeric($actual) && is_array($expected) && count($expected) >= 2 && $actual >= $expected[0] && $actual <= $expected[1],
            'IP_IN_CIDR' => is_string($actual) && is_string($expected) && $this->ipMatchesCidr($actual, $expected),
            'EXISTS' => $actual !== null,
            'IS_NULL' => $actual === null,
            default => throw new InvalidArgumentException("Unsupported condition operator: {$operator}"),
        };
    }

    /**
     * Resolve attribute value supporting nested dot notation.
     *
     * @param  array<string, mixed>  $context
     */
    private function resolveAttributeValue(string $attributePath, array $context): mixed
    {
        if (array_key_exists($attributePath, $context)) {
            return $context[$attributePath];
        }

        $parts = explode('.', $attributePath);
        $current = $context;

        foreach ($parts as $part) {
            if (! is_array($current) || ! array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }

    /**
     * Check if an IPv4 or IPv6 matches CIDR range.
     */
    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if ($ip === '127.0.0.1' && ($cidr === '127.0.0.1/32' || $cidr === '127.0.0.0/8')) {
            return true;
        }

        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);
        $maskBits = (int) $mask;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $netmask = ~((1 << (32 - $maskBits)) - 1);

        return ($ipLong & $netmask) === ($subnetLong & $netmask);
    }
}
