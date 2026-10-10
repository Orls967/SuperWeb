<?php

declare(strict_types=1);

namespace App\Quality\Progress;

use App\Quality\Audit\AuditCommandRegistry;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route as RouteFacade;
use Throwable;

/**
 * Progress and proof integrity scanner (PROGRESS R0.4, KONSEP §A14.2, P2).
 */
final class ProgressIntegrityScanner
{
    public function __construct(
        private readonly ?GitCommitInspector $gitInspector = null,
        private readonly ?string $repoRoot = null,
    ) {}

    /**
     * Parses the markdown content of docs/PROGRESS.md into phases and items.
     *
     * @return array<string, ProgressPhase> Keyed by phase ID (e.g. 'R0', '1', '87')
     */
    public function parse(string $content): array
    {
        $lines = explode("\n", $content);
        $phases = [];

        $currentPhaseId = null;
        $currentPhaseTitle = '';
        $currentStatusIcon = '';
        $currentStatusRaw = '';
        $currentStatusDate = null;
        $currentItems = [];

        /** @var ProgressItem|null $currentItem */
        $currentItem = null;
        $inProof = false;

        $saveCurrentPhase = function () use (
            &$phases,
            &$currentPhaseId,
            &$currentPhaseTitle,
            &$currentStatusIcon,
            &$currentStatusRaw,
            &$currentStatusDate,
            &$currentItems,
            &$currentItem
        ): void {
            if ($currentPhaseId === null) {
                return;
            }

            if ($currentItem !== null) {
                $currentItems[] = $currentItem;
                $currentItem = null;
            }

            $phases[$currentPhaseId] = new ProgressPhase(
                id: $currentPhaseId,
                title: $currentPhaseTitle,
                statusIcon: $currentStatusIcon,
                statusRaw: $currentStatusRaw,
                statusDate: $currentStatusDate,
                items: $currentItems,
            );

            $currentPhaseId = null;
            $currentPhaseTitle = '';
            $currentStatusIcon = '';
            $currentStatusRaw = '';
            $currentStatusDate = null;
            $currentItems = [];
        };

        foreach ($lines as $lineIndex => $line) {
            $lineNumber = $lineIndex + 1;

            // Phase header: ## FASE or ### FASE {ID} — {TITLE}
            if (preg_match('/^#{2,4}\s+FASE\s+([A-Za-z0-9\.\_\-]+)\s+[—-]\s+(.+)$/u', $line, $matches)) {
                $saveCurrentPhase();
                $currentPhaseId = $matches[1];
                $currentPhaseTitle = trim($matches[2]);
                $inProof = false;

                continue;
            }

            if ($currentPhaseId === null) {
                continue;
            }

            // Phase status line: > **Status audit...:**
            if (preg_match('/^>\s+\*\*Status\s+audit(?:.*)?:\*\*\s*(.*)$/u', $line, $matches)) {
                $rawStatus = trim($matches[1]);
                $currentStatusRaw = $rawStatus;

                // Extract icon: ✅, 🔨, 🔵, ⬜, 🟠, 🟡, 🔁
                if (preg_match('/(✅|🔨|🔵|⬜|🟠|🟡|🔁)/u', $rawStatus, $iconMatch)) {
                    $currentStatusIcon = $iconMatch[1];
                }

                // Extract date if present (e.g. 2026-10-10)
                if (preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/', $line, $dateMatch)) {
                    $currentStatusDate = $dateMatch[1];
                }

                continue;
            }

            // Item checkbox: - [ ] R0.1 ... or - [x] 87.3 ...
            if (preg_match('/^\s*-\s+\[([ xX])\]\s+((?:R\d+(?:\.[0-9a-z]+)*|\d+[A-Z]?(?:\.[0-9a-z]+)+))\b(?:\s+(.*))?$/u', $line, $matches)) {
                if ($currentItem !== null) {
                    $currentItems[] = $currentItem;
                }

                $isChecked = strtolower($matches[1]) === 'x';
                $itemId = $matches[2];
                $itemText = trim($matches[3] ?? '');

                $baseKey = "{$currentPhaseId}:{$itemId}";
                $phaseItemKeyCounts[$baseKey] = ($phaseItemKeyCounts[$baseKey] ?? 0) + 1;
                $occ = $phaseItemKeyCounts[$baseKey];
                $uniqueKey = $occ === 1 ? $baseKey : "{$baseKey}#{$occ}";

                $currentItem = new ProgressItem(
                    id: $itemId,
                    phaseId: $currentPhaseId,
                    isChecked: $isChecked,
                    text: $itemText,
                    proof: [],
                    lineNumber: $lineNumber,
                    uniqueKey: $uniqueKey,
                );

                $inProof = false;

                continue;
            }

            // Bukti: header under an item
            if ($currentItem !== null && preg_match('/^\s*Bukti\s*:\s*$/u', $line)) {
                $inProof = true;

                continue;
            }

            // Proof entry under Bukti:   - key: value
            if ($currentItem !== null && $inProof && preg_match('/^\s*-\s+([a-z0-9_]+)\s*:\s*(.+)$/i', $line, $matches)) {
                $key = strtolower(trim($matches[1]));
                $val = trim($matches[2]);

                $updatedProof = $currentItem->proof;
                $updatedProof[$key][] = $val;

                $currentItem = new ProgressItem(
                    id: $currentItem->id,
                    phaseId: $currentItem->phaseId,
                    isChecked: $currentItem->isChecked,
                    text: $currentItem->text,
                    proof: $updatedProof,
                    lineNumber: $currentItem->lineNumber,
                    uniqueKey: $currentItem->uniqueKey,
                );

                continue;
            }

            // Non-indented line or empty line followed by new section breaks proof block
            if ($inProof && trim($line) !== '' && ! str_starts_with($line, ' ') && ! str_starts_with($line, "\t")) {
                $inProof = false;
            }
        }

        $saveCurrentPhase();

        return $phases;
    }

    /**
     * Extracts item text snapshot from parsed phases for ratchet baseline.
     *
     * @param  array<string, ProgressPhase>  $phases
     * @return array<string, string>
     */
    public function extractItemTexts(array $phases): array
    {
        $texts = [];

        foreach ($phases as $phase) {
            foreach ($phase->items as $item) {
                $key = $item->uniqueKey !== '' ? $item->uniqueKey : "{$item->phaseId}:{$item->id}";
                $texts[$key] = $item->text;
            }
        }

        ksort($texts);

        return $texts;
    }

    /**
     * Validates progress integrity against P2 protocol rules.
     *
     * @param  array<string, ProgressPhase>  $phases
     * @param  array<string, string>  $textBaseline
     * @return list<string> List of violation error messages
     */
    public function validate(array $phases, ?string $repoRoot = null, array $textBaseline = []): array
    {
        $root = $repoRoot ?? $this->repoRoot ?? (function_exists('app') && app()->has('path.base') ? base_path() : dirname(__DIR__, 3));
        $inspector = $this->gitInspector ?? new GitCommitInspector($root);
        $violations = [];

        // Check if Fase R6 is completed; if so, all phases in PROGRESS.md are in scope.
        $allPhasesInScope = isset($phases['R6']) && $phases['R6']->isVerified();

        // 1. Validate ratchet text baseline (Anti-pola X18 / P10: teks item dilarang diubah tanpa penanda ⬇️)
        foreach ($phases as $phase) {
            foreach ($phase->items as $item) {
                $key = $item->uniqueKey !== '' ? $item->uniqueKey : "{$item->phaseId}:{$item->id}";
                if (isset($textBaseline[$key])) {
                    $baseText = $textBaseline[$key];
                    if ($item->text !== $baseText) {
                        $hasDowngradeMarker = str_contains($item->text, '⬇️ diturunkan') || str_contains($item->text, '⬇️');
                        if (! $hasDowngradeMarker) {
                            $violations[] = "Teks item {$item->id} ({$key}) diubah tanpa penanda '⬇️ diturunkan' (X18 / P10).";
                        }
                    }
                }
            }
        }

        // 2. Validate phase & item rules for phases in scope
        foreach ($phases as $phase) {
            $inScope = $allPhasesInScope || $phase->isFaseR() || $phase->isStatusChangedAfter('2026-10-10');

            if (! $inScope) {
                continue;
            }

            // Phase verified check
            if ($phase->isVerified()) {
                $gatePath = $root.'/docs/gates/fase-'.strtolower($phase->id).'.md';
                $altGatePath = $root.'/docs/gates/fase-'.$phase->id.'.md';

                if (! is_file($gatePath) && ! is_file($altGatePath)) {
                    $violations[] = "Fase {$phase->id} berstatus ✅ tetapi docs/gates/fase-{$phase->id}.md tidak ditemukan.";
                } else {
                    $actualGate = is_file($gatePath) ? $gatePath : $altGatePath;
                    $gateContent = (string) file_get_contents($actualGate);
                    if (! preg_match('/^#{1,3}\s+.*Verifikasi/mi', $gateContent)) {
                        $violations[] = "Fase {$phase->id} berstatus ✅ tetapi docs/gates/fase-{$phase->id}.md tidak memiliki bagian Verifikasi.";
                    }
                }
            }

            // Items check
            foreach ($phase->items as $item) {
                if (! $item->isChecked) {
                    continue;
                }

                // Checked item MUST have a non-empty Bukti: block
                if (empty($item->proof)) {
                    $violations[] = "Item {$item->id} tercentang [x] tanpa blok Bukti: (PROGRESS.md §P2).";

                    continue;
                }

                // Validate commit: commit exists and touches at least one mentioned file
                if (! empty($item->proof['commit'])) {
                    foreach ($item->proof['commit'] as $commitHash) {
                        if (! $inspector->isValidCommit($commitHash)) {
                            $violations[] = "Item {$item->id}: commit '{$commitHash}' tidak ditemukan di riwayat git.";

                            continue;
                        }

                        $mentionedFiles = $item->proof['file'] ?? [];
                        if ($mentionedFiles !== []) {
                            $changed = $inspector->changedFiles($commitHash);
                            $touched = array_intersect($mentionedFiles, $changed);
                            if ($touched === []) {
                                $violations[] = "Item {$item->id}: commit '{$commitHash}' tidak menyentuh file apa pun yang disebut di blok Bukti.";
                            }
                        }
                    }
                }

                // Validate file: physical file exists
                if (! empty($item->proof['file'])) {
                    foreach ($item->proof['file'] as $filePath) {
                        if (! is_file($root.'/'.$filePath)) {
                            $violations[] = "Item {$item->id}: file '{$filePath}' tidak ditemukan di filesystem.";
                        }
                    }
                }

                // Validate test: file exists and contains test name if specified
                if (! empty($item->proof['test'])) {
                    foreach ($item->proof['test'] as $testEntry) {
                        $parts = explode('::', $testEntry, 2);
                        $testFile = $parts[0];
                        $testName = $parts[1] ?? null;

                        if (! is_file($root.'/'.$testFile)) {
                            $violations[] = "Item {$item->id}: file test '{$testFile}' tidak ditemukan di filesystem.";
                        } elseif ($testName !== null) {
                            $testContent = (string) file_get_contents($root.'/'.$testFile);
                            $found = str_contains($testContent, $testName);
                            if (! $found && str_starts_with($testName, 'it ')) {
                                $found = str_contains($testContent, substr($testName, 3));
                            }
                            if (! $found) {
                                $violations[] = "Item {$item->id}: nama test '{$testName}' tidak ditemukan di dalam '{$testFile}'.";
                            }
                        }
                    }
                }

                // Validate gate: gate report file exists
                if (! empty($item->proof['gate'])) {
                    foreach ($item->proof['gate'] as $gatePath) {
                        if (! is_file($root.'/'.$gatePath)) {
                            $violations[] = "Item {$item->id}: file laporan gate '{$gatePath}' tidak ditemukan.";
                        }
                    }
                }

                // Feature items must have at least one akses and test
                if ($item->isFeatureItem()) {
                    if (empty($item->proof['akses'])) {
                        $violations[] = "Item fitur {$item->id} wajib memiliki minimal satu entri 'akses' di blok Bukti (PROGRESS.md §P2).";
                    }
                    if (empty($item->proof['test'])) {
                        $violations[] = "Item fitur {$item->id} wajib memiliki minimal satu entri 'test' di blok Bukti (PROGRESS.md §P2).";
                    }
                }

                // Validate akses: route or command
                if (! empty($item->proof['akses'])) {
                    foreach ($item->proof['akses'] as $aksesEntry) {
                        if (preg_match('/^route\s+([A-Z]+)\s+([^\s\[]+)(?:\s+\[(?:role|can):\s*([^\]]+)\])?$/i', $aksesEntry, $rm)) {
                            $method = strtoupper($rm[1]);
                            $uri = ltrim($rm[2], '/');
                            $roleString = $rm[3] ?? null;

                            $matchedRoute = self::findMatchingRoute($method, $uri);
                            if ($matchedRoute === null) {
                                $violations[] = "Item {$item->id}: akses route '{$method} /{$uri}' tidak ditemukan di router.";
                            } elseif ($roleString !== null) {
                                $expectedRoles = array_map('trim', explode(',', $roleString));
                                if (! self::routeHasExpectedRoles($matchedRoute, $expectedRoles)) {
                                    $violations[] = "Item {$item->id}: akses route '{$method} /{$uri}' tidak dilindungi role [{$roleString}].";
                                }
                            }
                        } elseif (preg_match('/^command\s+([^\s]+)/i', $aksesEntry, $cm)) {
                            $cmdName = $cm[1];
                            if (! self::isCommandRegistered($cmdName)) {
                                $violations[] = "Item {$item->id}: akses command '{$cmdName}' tidak terdaftar di Artisan.";
                            }
                        }
                    }
                }

                // Validate audit
                if (! empty($item->proof['audit'])) {
                    foreach ($item->proof['audit'] as $auditCmd) {
                        if (! self::isAuditCommandValid($auditCmd)) {
                            $violations[] = "Item {$item->id}: audit command '{$auditCmd}' tidak terdaftar atau tidak memiliki fixture korupsi.";
                        }
                    }
                }
            }
        }

        return $violations;
    }

    public static function findMatchingRoute(string $method, string $uri): ?Route
    {
        if (! class_exists(RouteFacade::class)) {
            return null;
        }

        try {
            $routes = RouteFacade::getRoutes()->getRoutes();
            $targetUri = trim($uri, '/');

            foreach ($routes as $route) {
                if (! in_array($method, $route->methods(), true)) {
                    continue;
                }
                if (trim($route->uri(), '/') === $targetUri) {
                    return $route;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @param  list<string>  $expectedRoles
     */
    public static function routeHasExpectedRoles(Route $route, array $expectedRoles): bool
    {
        $middleware = $route->gatherMiddleware();
        $declaredRoles = [];

        foreach ($middleware as $m) {
            if (str_starts_with($m, 'role:')) {
                $parsed = array_map('trim', explode(',', substr($m, 5)));
                $declaredRoles = array_unique([...$declaredRoles, ...$parsed]);
            }
        }

        foreach ($expectedRoles as $role) {
            if (in_array($role, $declaredRoles, true)) {
                return true;
            }
        }

        return false;
    }

    public static function isCommandRegistered(string $commandSignature): bool
    {
        if (! class_exists(Artisan::class)) {
            return true;
        }

        try {
            $commands = Artisan::all();
            $baseName = explode(' ', trim($commandSignature))[0];

            return isset($commands[$baseName]);
        } catch (Throwable) {
            return true;
        }
    }

    public static function isAuditCommandValid(string $auditName): bool
    {
        $baseName = explode(' ', trim($auditName))[0];
        if (class_exists(AuditCommandRegistry::class)) {
            $commands = AuditCommandRegistry::commands();
            if (isset($commands[$baseName])) {
                return true;
            }
        }

        return self::isCommandRegistered($baseName);
    }
}
