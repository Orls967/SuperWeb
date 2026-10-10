<?php

declare(strict_types=1);

namespace App\Quality\ArchScan;

/**
 * Result of one `arch:scan` run, grouped per rule.
 */
final class ScanReport
{
    /**
     * @param  array<string, string>  $descriptions  rule id => description
     * @param  array<string, list<Violation>>  $violations  rule id => violations
     */
    public function __construct(
        private readonly array $descriptions,
        private readonly array $violations,
    ) {}

    /**
     * @return list<string>
     */
    public function ruleIds(): array
    {
        return array_keys($this->descriptions);
    }

    public function description(string $ruleId): string
    {
        return $this->descriptions[$ruleId] ?? '';
    }

    /**
     * @return list<Violation>
     */
    public function violations(string $ruleId): array
    {
        return $this->violations[$ruleId] ?? [];
    }

    public function count(string $ruleId): int
    {
        return count($this->violations($ruleId));
    }

    /**
     * Violations keyed by fingerprint. Identical signatures in the same file are
     * numbered in source order (#1, #2, …).
     *
     * @return array<string, Violation>
     */
    public function fingerprints(string $ruleId): array
    {
        $occurrences = [];
        $fingerprints = [];

        foreach ($this->violations($ruleId) as $violation) {
            $key = $violation->path."\0".$violation->signature;
            $occurrences[$key] = ($occurrences[$key] ?? 0) + 1;
            $fingerprints[$violation->fingerprint($occurrences[$key])] = $violation;
        }

        return $fingerprints;
    }

    /**
     * @return array<string, array{description: string, count: int, violations: list<array{fingerprint: string, path: string, line: int, message: string}>}>
     */
    public function toArray(): array
    {
        $rules = [];

        foreach ($this->ruleIds() as $ruleId) {
            $items = [];

            foreach ($this->fingerprints($ruleId) as $fingerprint => $violation) {
                $items[] = [
                    'fingerprint' => $fingerprint,
                    'path' => $violation->path,
                    'line' => $violation->line,
                    'message' => $violation->message,
                ];
            }

            $rules[$ruleId] = [
                'description' => $this->description($ruleId),
                'count' => count($items),
                'violations' => $items,
            ];
        }

        return $rules;
    }
}
