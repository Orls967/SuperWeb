<?php

declare(strict_types=1);

namespace App\Quality\Progress;

class MassCertificationGuard
{
    public function __construct(
        protected ?string $repoRoot = null,
        protected ?GitCommitInspector $gitInspector = null,
    ) {
        $this->repoRoot = $repoRoot ?? (function_exists('base_path') ? base_path() : dirname(__DIR__, 3));
        $this->gitInspector = $gitInspector ?? new GitCommitInspector($this->repoRoot);
    }

    /**
     * @return array<int, string>
     */
    public function validate(
        ?string $repoRoot = null,
        ?string $baselinePath = null,
        string $fromCommit = '8c8369d',
        string $toCommit = 'HEAD',
    ): array {
        $root = $repoRoot ?? $this->repoRoot;
        $baselineFile = $baselinePath ?? $root.'/tests/Architecture/baselines/mass-certification.json';

        $allowlistedCommits = [];
        if (is_file($baselineFile)) {
            $baselineData = json_decode((string) file_get_contents($baselineFile), true);
            $allowlistedCommits = $baselineData['commits'] ?? [];
        }

        $decisionsPath = $root.'/docs/DECISIONS.md';
        $decisionsContent = is_file($decisionsPath) ? (string) file_get_contents($decisionsPath) : '';

        // Query commits touching docs/PROGRESS.md between $fromCommit and $toCommit
        $commits = $this->getCommitsTouchingProgress($root, $fromCommit, $toCommit);

        $violations = [];

        foreach ($commits as $commit) {
            $sha = $commit['sha'];
            $subject = $commit['subject'];

            // If already in baseline allowlist, skip
            if (in_array($sha, $allowlistedCommits, true) || in_array(substr($sha, 0, 8), $allowlistedCommits, true)) {
                continue;
            }

            $message = $this->getCommitMessage($root, $sha);
            $hasDecisionAnchor = (bool) preg_match('/DECISIONS\.md#([a-zA-Z0-9_\-]+)/u', $message, $anchorMatch);
            if ($hasDecisionAnchor) {
                $anchor = $anchorMatch[1];
                if (! str_contains($decisionsContent, $anchor)) {
                    $violations[] = "Commit {$sha} ({$subject}): anchor '{$anchor}' tidak ditemukan di docs/DECISIONS.md.";
                }
            }

            $diff = $this->getCommitProgressDiff($root, $sha);

            // 1. Added [x] check (> 15 items [ ] -> [x])
            $addedChecked = preg_match_all('/^\+\s*-\s*\[x\]/m', $diff, $dummy);
            if ($addedChecked > 15 && ! $hasDecisionAnchor) {
                $violations[] = "Commit {$sha} ({$subject}): menambah {$addedChecked} centang [x] (> 15) tanpa anchor DECISIONS.md#... di pesan commit (K-8).";
            }

            // 2. Phase verified check (> 1 phase -> ✅)
            $addedVerified = preg_match_all('/^\+\s*(?:>\s*)?\*\*Status\s+audit[^\n]*✅/mu', $diff, $dummy2);
            if ($addedVerified > 1 && ! $hasDecisionAnchor) {
                $violations[] = "Commit {$sha} ({$subject}): memverifikasi {$addedVerified} fase menjadi ✅ (> 1) tanpa anchor DECISIONS.md#... di pesan commit (K-8).";
            }

            // 3. Any phase verified -> must touch only docs/** and subject must start with docs(verifikasi-
            if ($addedVerified >= 1) {
                $touchedFiles = $this->getCommitChangedFiles($root, $sha);
                $outsideDocs = array_filter($touchedFiles, fn (string $f) => ! str_starts_with($f, 'docs/'));
                if ($outsideDocs !== []) {
                    $violations[] = "Commit {$sha} ({$subject}): memverifikasi status fase ✅ tetapi menyentuh file di luar docs/ (".implode(', ', array_slice($outsideDocs, 0, 3)).') (K-8).';
                }

                if (! str_starts_with($subject, 'docs(verifikasi-')) {
                    $violations[] = "Commit {$sha} ({$subject}): memverifikasi status fase ✅ tetapi judul commit tidak berawalan 'docs(verifikasi-' (K-8).";
                }
            }
        }

        return $violations;
    }

    /**
     * @return array<int, array{sha: string, subject: string}>
     */
    protected function getCommitsTouchingProgress(string $root, string $from, string $to): array
    {
        $cmd = 'git -C '.escapeshellarg($root).' log '.escapeshellarg("{$from}..{$to}").' '.escapeshellarg('--format=%H|%s').' -- docs/PROGRESS.md';
        $out = shell_exec($cmd);
        if ($out === null || trim($out) === '') {
            return [];
        }

        $commits = [];
        foreach (explode("\n", trim($out)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line, 2);
            $commits[] = [
                'sha' => $parts[0],
                'subject' => $parts[1] ?? '',
            ];
        }

        return $commits;
    }

    protected function getCommitMessage(string $root, string $sha): string
    {
        $cmd = 'git -C '.escapeshellarg($root).' log -1 --format=%B '.escapeshellarg($sha);

        return (string) shell_exec($cmd);
    }

    protected function getCommitProgressDiff(string $root, string $sha): string
    {
        $cmd = 'git -C '.escapeshellarg($root).' diff '.escapeshellarg("{$sha}~1").' '.escapeshellarg($sha).' -- docs/PROGRESS.md';

        return (string) shell_exec($cmd);
    }

    /**
     * @return array<int, string>
     */
    protected function getCommitChangedFiles(string $root, string $sha): array
    {
        $cmd = 'git -C '.escapeshellarg($root).' diff-tree --no-commit-id --name-only -r '.escapeshellarg($sha);
        $out = shell_exec($cmd);
        if ($out === null || trim($out) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", trim($out)))));
    }
}
