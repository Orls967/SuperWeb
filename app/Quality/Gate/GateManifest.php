<?php

declare(strict_types=1);

namespace App\Quality\Gate;

/**
 * Value object and parser for storage/logs/gate-manifest.json (PROGRESS R0.2, P4).
 */
final class GateManifest
{
    /**
     * Required steps that MUST be executed in composer gate.
     */
    public const REQUIRED_STEP_KEYS = [
        'composer validate --strict',
        'pest',
        'pint --test',
        'arch:scan --json',
        'migrate:fresh --seed',
        'bank:reconcile',
        'chain:audit-all',
        'super:health-check',
        'npm run build',
    ];

    /**
     * @param  list<array{name: string, command: string, exit_code: int, duration: float, status: 'PASS'|'FAIL'}>  $steps
     */
    public function __construct(
        public readonly string $commit,
        public readonly bool $isDirty,
        public readonly string $timestamp,
        public readonly string $overallStatus,
        public readonly array $steps = [],
    ) {}

    public static function fromJson(string $json): ?self
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return null;
        }

        return new self(
            commit: (string) ($data['commit'] ?? ''),
            isDirty: (bool) ($data['is_dirty'] ?? true),
            timestamp: (string) ($data['timestamp'] ?? ''),
            overallStatus: (string) ($data['overall_status'] ?? 'FAIL'),
            steps: is_array($data['steps'] ?? null) ? $data['steps'] : [],
        );
    }

    public static function fromFile(string $path): ?self
    {
        if (! is_file($path)) {
            return null;
        }

        return self::fromJson((string) file_get_contents($path));
    }

    /**
     * Checks if all steps passed with exit code 0 and overall status is PASS.
     */
    public function isClean(): bool
    {
        if ($this->overallStatus !== 'PASS') {
            return false;
        }

        foreach ($this->steps as $step) {
            if (($step['exit_code'] ?? 1) !== 0 || ($step['status'] ?? 'FAIL') !== 'PASS') {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks whether all mandatory steps are present in the manifest.
     *
     * @return list<string> List of missing step names, if any.
     */
    public function missingRequiredSteps(): array
    {
        $executedNames = [];
        $executedCommands = [];
        foreach ($this->steps as $step) {
            $name = strtolower(trim((string) ($step['name'] ?? '')));
            $cmd = strtolower(trim((string) ($step['command'] ?? '')));
            if ($name !== '') {
                $executedNames[] = $name;
            }
            if ($cmd !== '') {
                $executedCommands[] = $cmd;
            }
        }

        $missing = [];
        foreach (self::REQUIRED_STEP_KEYS as $required) {
            $search = strtolower(trim($required));
            $found = in_array($search, $executedNames, true);
            if (! $found) {
                foreach ($executedCommands as $cmd) {
                    if ($cmd === $search
                        || str_starts_with($cmd, $search.' ')
                        || str_starts_with($cmd, 'php artisan '.$search)
                        || str_starts_with($cmd, 'vendor/bin/'.$search)
                    ) {
                        $found = true;
                        break;
                    }
                }
            }
            if (! $found) {
                $missing[] = $required;
            }
        }

        return $missing;
    }

    /**
     * Checks if the manifest commit matches the current git HEAD commit.
     */
    public function matchesCommit(string $expectedCommit): bool
    {
        $cleanExpected = strtolower(trim($expectedCommit));
        $cleanActual = strtolower(trim($this->commit));

        if ($cleanExpected === '' || $cleanActual === '') {
            return false;
        }

        return str_starts_with($cleanExpected, $cleanActual) || str_starts_with($cleanActual, $cleanExpected);
    }

    /**
     * Checks if the working tree was clean when the gate ran.
     */
    public function isWorkingTreeClean(): bool
    {
        return ! $this->isDirty;
    }

    /**
     * Finds the failed step details, if any.
     *
     * @return array{name: string, command: string, exit_code: int, duration: float, status: 'PASS'|'FAIL'}|null
     */
    public function firstFailedStep(): ?array
    {
        foreach ($this->steps as $step) {
            if (($step['exit_code'] ?? 1) !== 0 || ($step['status'] ?? 'FAIL') !== 'PASS') {
                return $step;
            }
        }

        return null;
    }

    /**
     * Converts manifest to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'commit' => $this->commit,
            'is_dirty' => $this->isDirty,
            'timestamp' => $this->timestamp,
            'overall_status' => $this->overallStatus,
            'steps' => $this->steps,
        ];
    }
}
