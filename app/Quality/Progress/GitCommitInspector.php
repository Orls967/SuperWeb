<?php

declare(strict_types=1);

namespace App\Quality\Progress;

/**
 * Git repository inspector for commit verification (PROGRESS R0.4, P2).
 */
class GitCommitInspector
{
    public function __construct(private ?string $repoRoot = null)
    {
        $this->repoRoot ??= function_exists('app') && app()->has('path.base') ? base_path() : dirname(__DIR__, 3);
    }

    /**
     * Checks if a commit hash exists in git history.
     */
    public function isValidCommit(string $hash): bool
    {
        $cleanHash = escapeshellarg(trim($hash));
        $cmd = 'git -C '.escapeshellarg((string) $this->repoRoot)." rev-parse --verify --quiet {$cleanHash}^{commit} 2>/dev/null";
        $output = shell_exec($cmd);

        return $output !== null && trim($output) !== '';
    }

    /**
     * Returns list of file paths touched by the commit.
     *
     * @return list<string>
     */
    public function changedFiles(string $hash): array
    {
        $cleanHash = escapeshellarg(trim($hash));
        $cmd = 'git -C '.escapeshellarg((string) $this->repoRoot)." diff-tree --no-commit-id --name-only -r {$cleanHash} 2>/dev/null";
        $output = shell_exec($cmd);

        if ($output === null || trim($output) === '') {
            return [];
        }

        $lines = array_map('trim', explode("\n", trim($output)));

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    /**
     * Returns list of files changed between two commits.
     *
     * @return list<string>
     */
    public function diffFiles(string $fromCommit, string $toCommit = 'HEAD'): array
    {
        $cleanFrom = escapeshellarg(trim($fromCommit));
        $cleanTo = escapeshellarg(trim($toCommit));
        $cmd = 'git -C '.escapeshellarg((string) $this->repoRoot)." diff --name-only {$cleanFrom} {$cleanTo} 2>/dev/null";
        $output = shell_exec($cmd);

        if ($output === null || trim($output) === '') {
            return [];
        }

        $lines = array_map('trim', explode("\n", trim($output)));

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }
}
