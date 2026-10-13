<?php

declare(strict_types=1);

namespace App\Quality\Gate;

use SimpleXMLElement;
use Throwable;

/**
 * Parses PHPUnit / Pest JUnit XML output files into a JUnitSummary (PROGRESS R0.2).
 */
final class JUnitParser
{
    /**
     * Parses JUnit XML string content.
     */
    public static function parse(string $xmlContent): JUnitSummary
    {
        try {
            $xml = new SimpleXMLElement($xmlContent);
        } catch (Throwable $e) {
            throw new \InvalidArgumentException('Konten JUnit XML tidak valid: '.$e->getMessage(), 0, $e);
        }

        $totalTests = 0;
        $totalFailures = 0;
        $totalErrors = 0;
        $totalAssertions = 0;
        $duration = 0.0;
        $testCases = [];

        $rootTests = (int) ($xml['tests'] ?? 0);
        $rootFailures = (int) ($xml['failures'] ?? 0);
        $rootErrors = (int) ($xml['errors'] ?? 0);
        $rootAssertions = (int) ($xml['assertions'] ?? 0);
        $rootTime = (float) ($xml['time'] ?? 0.0);

        // Accumulate from all child <testsuite> nodes if root doesn't have them
        $suiteNodes = $xml->xpath('//testsuite') ?: [];
        $suiteTests = 0;
        $suiteFailures = 0;
        $suiteErrors = 0;
        $suiteAssertions = 0;
        $suiteTime = 0.0;

        foreach ($suiteNodes as $suite) {
            // If this suite contains other suites, don't double count
            $childrenSuites = $suite->xpath('./testsuite');
            if (! empty($childrenSuites)) {
                continue;
            }
            $suiteTests += (int) ($suite['tests'] ?? 0);
            $suiteFailures += (int) ($suite['failures'] ?? 0);
            $suiteErrors += (int) ($suite['errors'] ?? 0);
            $suiteAssertions += (int) ($suite['assertions'] ?? 0);
            $suiteTime += (float) ($suite['time'] ?? 0.0);
        }

        $totalTests = $rootTests > 0 ? $rootTests : $suiteTests;
        $totalFailures = $rootFailures > 0 ? $rootFailures : $suiteFailures;
        $totalErrors = $rootErrors > 0 ? $rootErrors : $suiteErrors;
        $totalAssertions = $rootAssertions > 0 ? $rootAssertions : $suiteAssertions;
        $duration = $rootTime > 0.0 ? $rootTime : $suiteTime;

        // Iterate all <testcase> nodes recursively
        $caseNodes = $xml->xpath('//testcase') ?: [];
        $calculatedTests = 0;
        $calculatedFailures = 0;
        $calculatedErrors = 0;
        $calculatedAssertions = 0;
        $calculatedTime = 0.0;

        foreach ($caseNodes as $case) {
            $name = (string) ($case['name'] ?? '');
            $class = (string) ($case['class'] ?? '');
            $file = isset($case['file']) ? (string) $case['file'] : null;
            $time = (float) ($case['time'] ?? 0.0);
            $assertions = (int) ($case['assertions'] ?? 1);

            $status = 'PASS';
            if (isset($case->failure)) {
                $status = 'FAIL';
                $calculatedFailures++;
            } elseif (isset($case->error)) {
                $status = 'ERROR';
                $calculatedErrors++;
            } elseif (isset($case->skipped)) {
                $status = 'SKIPPED';
            }

            $calculatedTests++;
            $calculatedAssertions += $assertions;
            $calculatedTime += $time;

            $record = [
                'name' => $name,
                'class' => $class,
                'file' => $file,
                'status' => $status,
                'time' => $time,
            ];

            $testCases[$name] = $record;
            if ($class !== '') {
                $testCases["{$class}::{$name}"] = $record;
            }
        }

        return new JUnitSummary(
            totalTests: $totalTests > 0 ? $totalTests : $calculatedTests,
            totalFailures: $totalFailures > 0 ? $totalFailures : $calculatedFailures,
            totalErrors: $totalErrors > 0 ? $totalErrors : $calculatedErrors,
            totalAssertions: $totalAssertions > 0 ? $totalAssertions : $calculatedAssertions,
            duration: $duration > 0.0 ? $duration : $calculatedTime,
            testCases: $testCases,
        );
    }

    /**
     * Parses JUnit XML from a file path.
     */
    public static function parseFile(string $filePath): JUnitSummary
    {
        if (! is_file($filePath)) {
            throw new \InvalidArgumentException("File JUnit XML '{$filePath}' tidak ditemukan.");
        }

        $content = (string) file_get_contents($filePath);

        return self::parse($content);
    }
}
