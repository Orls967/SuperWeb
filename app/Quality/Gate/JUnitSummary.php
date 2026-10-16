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
        $fileClean = $file !== null ? strtolower(str_replace(['/', '\\', '.php'], '', $file)) : null;

        $searchVariants = array_unique(array_filter([
            $search,
            str_replace('_', ' ', $search),
            (string) preg_replace('/^test[\s_]+/', '', $search),
            (string) preg_replace('/^test[\s_]+/', '', str_replace('_', ' ', $search)),
            str_replace(' ', '_', $search),
        ]));

        foreach ($this->testCases as $key => $case) {
            $caseName = strtolower($case['name']);
            $keyLower = strtolower($key);
            $caseVariants = array_unique(array_filter([
                $caseName,
                str_replace('_', ' ', $caseName),
                (string) preg_replace('/^test[\s_]+/', '', $caseName),
                (string) preg_replace('/^test[\s_]+/', '', str_replace('_', ' ', $caseName)),
                str_replace(' ', '_', $caseName),
            ]));

            $matched = false;
            foreach ($searchVariants as $sv) {
                if ($sv === '') {
                    continue;
                }
                if (str_contains($keyLower, $sv)) {
                    $matched = true;
                    break;
                }
                foreach ($caseVariants as $cv) {
                    if ($cv !== '' && (str_contains($cv, $sv) || str_contains($sv, $cv))) {
                        $matched = true;
                        break 2;
                    }
                }
            }

            if ($matched) {
                if ($file === null || $case['file'] === null || str_contains($case['file'], $file)) {
                    return $case;
                }

                if ($fileClean !== null) {
                    $caseFileClean = strtolower(str_replace(['/', '\\', '.php'], '', $case['file'] ?? ''));
                    $caseClassClean = strtolower(str_replace(['/', '\\', '.php'], '', $case['class'] ?? ''));
                    if (str_contains($caseFileClean, $fileClean) || str_contains($caseClassClean, $fileClean) || str_contains($fileClean, $caseClassClean)) {
                        return $case;
                    }
                }
            }
        }

        return null;
    }
}
