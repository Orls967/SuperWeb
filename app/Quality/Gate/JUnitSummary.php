<?php

declare(strict_types=1);

namespace App\Quality\Gate;

/**
 * Summary of a JUnit XML test run report (PROGRESS R0.2).
 */
final class JUnitSummary
{
    /**
     * @param  array<string, array{name: string, class: string, file: ?string, status: 'PASS'|'FAIL'|'ERROR'|'SKIPPED', time: float}>  $testCases  Keyed by test name or class::name
     */
    public function __construct(
        public readonly int $totalTests,
        public readonly int $totalFailures,
        public readonly int $totalErrors,
        public readonly int $totalAssertions,
        public readonly float $duration,
        public readonly array $testCases = [],
    ) {}

    public function isClean(): bool
    {
        return $this->totalFailures === 0 && $this->totalErrors === 0;
    }

    /**
     * Finds the execution result of a test case mentioned in proof blocks.
     *
     * @return array{name: string, class: string, file: ?string, status: 'PASS'|'FAIL'|'ERROR'|'SKIPPED', time: float}|null
     */
    public function findTestCase(string $nameOrSignature, ?string $file = null): ?array
    {
        // 1. Direct match by key
        if (isset($this->testCases[$nameOrSignature])) {
            return $this->testCases[$nameOrSignature];
        }

        // 2. Partial match on name
        $search = strtolower(trim($nameOrSignature));
        foreach ($this->testCases as $key => $case) {
            $caseName = strtolower($case['name']);
            if (str_contains($caseName, $search) || str_contains(strtolower($key), $search)) {
                if ($file === null || $case['file'] === null || str_contains($case['file'], $file)) {
                    return $case;
                }
            }
        }

        return null;
    }
}
