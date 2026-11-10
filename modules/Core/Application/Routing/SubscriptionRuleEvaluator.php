<?php

declare(strict_types=1);

namespace Modules\Core\Application\Routing;

use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Evaluator Aturan Langganan Kompleks (SubscriptionRuleEvaluator)
 *
 * Menerapkan evaluasi predikat bersyarat (boolean expression engine) pada event spine:
 * Mendukung operator perbandingan: EQ, NEQ, GT, GTE, LT, LTE, IN, CONTAINS, PREFIX, EXISTS,
 * dan operator logika: AND, OR, NOT.
 */
class SubscriptionRuleEvaluator
{
    /**
     * Mengevaluasi apakah event spine memenuhi sekumpulan aturan filter terstruktur.
     *
     * @param  array<string, mixed>  $filterRule  Struktur aturan: ['op' => 'AND', 'conditions' => [...]]
     */
    public function evaluate(SimEventSpine $event, array $filterRule): bool
    {
        if (empty($filterRule)) {
            return true;
        }

        $op = strtoupper((string) ($filterRule['op'] ?? 'AND'));
        $conditions = (array) ($filterRule['conditions'] ?? []);

        if (empty($conditions) && isset($filterRule['field'])) {
            // Single leaf condition
            return $this->evaluateCondition($event, $filterRule);
        }

        if ($op === 'AND') {
            foreach ($conditions as $subRule) {
                if (! $this->evaluate($event, $subRule)) {
                    return false;
                }
            }

            return true;
        }

        if ($op === 'OR') {
            foreach ($conditions as $subRule) {
                if ($this->evaluate($event, $subRule)) {
                    return true;
                }
            }

            return false;
        }

        if ($op === 'NOT') {
            $first = reset($conditions);

            return is_array($first) ? ! $this->evaluate($event, $first) : true;
        }

        return false;
    }

    /**
     * Mengevaluasi kondisi tunggal pada field atau atribut event.
     *
     * @param  array<string, mixed>  $cond
     */
    protected function evaluateCondition(SimEventSpine $event, array $cond): bool
    {
        $field = (string) ($cond['field'] ?? '');
        $operator = strtoupper((string) ($cond['operator'] ?? 'EQ'));
        $expected = $cond['value'] ?? null;

        $actual = $this->resolveFieldValue($event, $field);

        return match ($operator) {
            'EQ' => $actual == $expected,
            'NEQ' => $actual != $expected,
            'GT' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'GTE' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'LT' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'LTE' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            'IN' => is_array($expected) && in_array($actual, $expected, false),
            'NOT_IN' => is_array($expected) && ! in_array($actual, $expected, false),
            'CONTAINS' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'PREFIX' => is_string($actual) && is_string($expected) && str_starts_with($actual, $expected),
            'EXISTS' => $actual !== null,
            'NOT_EXISTS' => $actual === null,
            default => false,
        };
    }

    /**
     * Mengambil nilai field dari header model atau payload berformat dot notation (misal 'payload.amount').
     */
    protected function resolveFieldValue(SimEventSpine $event, string $field): mixed
    {
        if ($field === 'topic') {
            return $event->topic;
        }
        if ($field === 'aggregate_type') {
            return $event->aggregate_type;
        }
        if ($field === 'aggregate_id') {
            return $event->aggregate_id;
        }
        if ($field === 'sequence') {
            return $event->sequence;
        }

        // Cari dalam payload menggunakan dot-notation
        $path = str_starts_with($field, 'payload.') ? substr($field, 8) : $field;
        $payload = (array) $event->payload;

        return data_get($payload, $path);
    }
}

