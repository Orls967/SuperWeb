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
        $currentMinusEntries = [];
        $phaseItemKeyCounts = [];

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
            &$currentMinusEntries,
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
                minusEntries: $currentMinusEntries,
            );

            $currentPhaseId = null;
            $currentPhaseTitle = '';
            $currentStatusIcon = '';
            $currentStatusRaw = '';
            $currentStatusDate = null;
            $currentItems = [];
            $currentMinusEntries = [];
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

            // Proof block header: Bukti:
            if (preg_match('/^\s*Bukti:\s*$/u', $line)) {
                $inProof = true;

                continue;
            }

            // Proof line: - key: value
            if ($inProof && $currentItem !== null && preg_match('/^\s*-\s+([a-z]+):\s*(.+)$/u', $line, $matches)) {
                $key = strtolower(trim($matches[1]));
                $value = trim($matches[2]);

                $currentProof = $currentItem->proof;
                $currentProof[$key][] = $value;

                $currentItem = new ProgressItem(
                    id: $currentItem->id,
                    phaseId: $currentItem->phaseId,
                    isChecked: $currentItem->isChecked,
                    text: $currentItem->text,
                    proof: $currentProof,
                    lineNumber: $currentItem->lineNumber,
                    uniqueKey: $currentItem->uniqueKey,
                );

                continue;
            }

            // Register Minus table row: | M-N-1 | N.3 | minus | dampak | P0 | rencana |
            if (preg_match('/^\|\s*([~]*M-[^|]+[~]*)\s*\|\s*([^|]+)\|\s*([^|]+)\|\s*([^|]+)\|\s*([^|]+)\|\s*([^|]+)\|/u', $line, $mm)) {
                $rawId = trim($mm[1]);
                $isClosed = str_contains($rawId, '~~') || str_contains($line, '~~');
                $cleanId = trim(str_replace('~~', '', $rawId));
                $currentMinusEntries[] = [
                    'id' => $cleanId,
                    'item' => trim($mm[2]),
                    'minus' => trim($mm[3]),
                    'dampak' => trim($mm[4]),
                    'prioritas' => trim(str_replace('~~', '', $mm[5])),
                    'rencana' => trim($mm[6]),
                    'is_closed' => $isClosed,
                ];

                continue;
            }

            // If a non-indented or non-proof line is encountered, exit proof block
            if ($inProof && ! preg_match('/^\s{2,}/u', $line)) {
                $inProof = false;
            }
        }

        $saveCurrentPhase();

        return $phases;
    }

    /**
     * Extracts baseline text dictionary of all items in PROGRESS.md for ratchet locking.
     *
     * @return array<string, string> Keyed by item uniqueKey (or 'phaseId:itemId')
     */
    public function extractTextBaseline(string $content): array
    {
        $phases = $this->parse($content);
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
    public function validate(
        array $phases,
        ?string $repoRoot = null,
        array $textBaseline = [],
        ?array $snapshot = null,
    ): array {
        $root = $repoRoot ?? $this->repoRoot ?? (function_exists('app') && app()->has('path.base') ? base_path() : dirname(__DIR__, 3));
        $inspector = $this->gitInspector ?? new GitCommitInspector($root);
        $violations = [];

        // Check if Fase R6 is completed; if so, all phases in PROGRESS.md are in scope.
        $allPhasesInScope = isset($phases['R6']) && $phases['R6']->isVerified();

        if ($snapshot !== null) {
            $snapshotPhases = $snapshot['phases'] ?? [];
            $snapshotItems = $snapshot['items'] ?? [];
        } else {
            $snapshotPath = $root.'/tests/Architecture/baselines/progress-snapshot-8c8369d.json';
            $snapshotData = is_file($snapshotPath)
                ? json_decode((string) file_get_contents($snapshotPath), true)
                : [];
            $snapshotPhases = $snapshotData['phases'] ?? [];
            $snapshotItems = $snapshotData['items'] ?? [];
        }

        $decisionsPath = $root.'/docs/DECISIONS.md';
        $decisionsContent = is_file($decisionsPath) ? (string) file_get_contents($decisionsPath) : '';

        // 1. Validate ratchet text baseline (Anti-pola X18 / P10: teks item dilarang diubah tanpa penanda ⬇️)
        foreach ($phases as $phase) {
            foreach ($phase->items as $item) {
                $key = $item->uniqueKey !== '' ? $item->uniqueKey : "{$item->phaseId}:{$item->id}";
                $snapItem = $snapshotItems[$key] ?? null;
                $baseText = $textBaseline !== [] ? ($textBaseline[$key] ?? null) : ($snapItem['text'] ?? null);

                if ($baseText !== null && $item->text !== $baseText) {
                    $hasValidDowngrade = preg_match('/⬇️\s*diturunkan:\s*(.+)$/u', $item->text, $dm) && mb_strlen(trim($dm[1])) >= 10;
                    if (! $hasValidDowngrade) {
                        $violations[] = "Teks item {$item->id} ({$key}) diubah tanpa penanda '⬇️ diturunkan: <alasan>' dengan alasan minimal 10 karakter (X18 / P10).";
                    }

                    if (! str_contains($decisionsContent, $item->id)) {
                        $violations[] = "Teks item {$item->id} diturunkan tetapi ID item tidak disebutkan di docs/DECISIONS.md.";
                    }
                }
            }
        }

        // 2. Validate phase & item rules for phases in scope
        foreach ($phases as $phase) {
            $phaseIconChanged = isset($snapshotPhases[$phase->id]) && $snapshotPhases[$phase->id] !== $phase->statusIcon;

            $itemCheckedTransition = false;
            foreach ($phase->items as $item) {
                $key = $item->uniqueKey !== '' ? $item->uniqueKey : "{$item->phaseId}:{$item->id}";
                $snapItem = $snapshotItems[$key] ?? null;
                if ($snapItem !== null && ! ($snapItem['checked'] ?? false) && $item->isChecked) {
                    $itemCheckedTransition = true;
                    break;
                }
            }

            $inScope = $allPhasesInScope
                || $phase->isFaseR()
                || $phase->isStatusChangedAfter('2026-10-10')
                || $phaseIconChanged
                || $itemCheckedTransition;

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
                    if (! preg_match('/^#{1,3}\s+.*Verifikasi.*$/mi', $gateContent, $matches, PREG_OFFSET_CAPTURE)) {
                        $violations[] = "Fase {$phase->id} berstatus ✅ tetapi docs/gates/fase-{$phase->id}.md tidak memiliki bagian Verifikasi.";
                    } else {
                        $verifSection = substr($gateContent, $matches[0][1]);

                        // Validasi tanggal verifikasi
                        if (! preg_match('/-\s+\*\*Tanggal(?:\s+Verifikasi)?:\*\*\s*([^\s\r\n].*)/iu', $verifSection, $tm) || trim($tm[1]) === '') {
                            $violations[] = "Fase {$phase->id} berstatus ✅ tetapi bagian Verifikasi di {$actualGate} tidak sah: tanggal verifikasi kosong atau tidak ditemukan.";
                        }

                        // Validasi commit yang diverifikasi
                        if (! preg_match('/-\s+\*\*Commit(?:\s+yang\s+diverifikasi)?:\*\*\s*([^\s\r\n].*)/iu', $verifSection, $cm) || trim($cm[1]) === '') {
                            $violations[] = "Fase {$phase->id} berstatus ✅ tetapi bagian Verifikasi di {$actualGate} tidak sah: commit yang diverifikasi kosong atau tidak ditemukan.";
                        }

                        // Validasi verifikator
                        if (! preg_match('/-\s+\*\*Verifikator:\*\*\s*([^\s\r\n].*)/iu', $verifSection, $vm) || trim($vm[1]) === '') {
                            $violations[] = "Fase {$phase->id} berstatus ✅ tetapi bagian Verifikasi di {$actualGate} tidak sah: identitas verifikator kosong atau tidak ditemukan.";
                        }

                        // Validasi tabel C1–C14 bertanda lulus/gagal
                        for ($i = 1; $i <= 14; $i++) {
                            if (! preg_match('/\|\s*C'.$i.'\s*\|\s*([^|]+)\|/i', $verifSection, $rm)) {
                                $violations[] = "Fase {$phase->id} berstatus ✅ tetapi bagian Verifikasi di {$actualGate} tidak sah: butir C{$i} tidak ditemukan di tabel verifikasi.";
                            } else {
                                $result = strtolower(trim($rm[1]));
                                if (str_contains($result, 'belum diverifikasi') || (! str_contains($result, 'lulus') && ! str_contains($result, 'gagal') && ! str_contains($result, 'pass') && ! str_contains($result, 'fail'))) {
                                    $violations[] = "Fase {$phase->id} berstatus ✅ tetapi bagian Verifikasi di {$actualGate} tidak sah: butir C{$i} belum bertanda lulus/gagal ('".trim($rm[1])."').";
                                }
                            }
                        }
                    }
                }

                $openCritical = $phase->openCriticalMinusIds();
                if ($openCritical !== []) {
                    $violations[] = "Fase {$phase->id} berstatus ✅ tetapi masih memiliki minus P0/P1 terbuka: ".implode(', ', $openCritical).' (PROGRESS.md §P7, P9).';
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

                // V3: Validasi jenis item
                $itemType = $item->itemType();
                if ($itemType === 'fitur') {
                    if (empty($item->proof['akses'])) {
                        $violations[] = "Item fitur {$item->id} wajib memiliki minimal satu entri 'akses' di blok Bukti (PROGRESS.md §P2).";
                    }
                    if (empty($item->proof['test'])) {
                        $violations[] = "Item fitur {$item->id} wajib memiliki minimal satu entri 'test' di blok Bukti (PROGRESS.md §P2).";
                    }
                } elseif (in_array($itemType, ['tooling', 'konfigurasi'], true)) {
                    if (empty($item->proof['test'])) {
                        $violations[] = "Item {$itemType} {$item->id} wajib memiliki minimal satu entri 'test' di blok Bukti (PROGRESS.md §P2).";
                    }
                } elseif ($itemType === 'dokumen') {
                    if (empty($item->proof['file'])) {
                        $violations[] = "Item dokumen {$item->id} wajib memiliki minimal satu entri 'file' di blok Bukti (PROGRESS.md §P2).";
                    }
                } else {
                    $violations[] = "Item {$item->id}: jenis '{$itemType}' tidak valid (hanya fitur, tooling, konfigurasi, dokumen).";
                }

                // V3: Validasi panjang alasan bila ada
                if (! empty($item->proof['alasan'])) {
                    foreach ($item->proof['alasan'] as $reason) {
                        if (mb_strlen(trim($reason)) < 10) {
                            $violations[] = "Item {$item->id}: alasan '{$reason}' kurang dari 10 karakter.";
                        }
                    }
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

                // Validate test: file exists and contains exact test declaration
                if (! empty($item->proof['test'])) {
                    foreach ($item->proof['test'] as $testEntry) {
                        $parts = explode('::', $testEntry, 2);
                        $testFile = $parts[0];
                        $testName = $parts[1] ?? null;

                        if (! is_file($root.'/'.$testFile)) {
                            $violations[] = "Item {$item->id}: file test '{$testFile}' tidak ditemukan di filesystem.";
                        } elseif ($testName !== null) {
                            if (! self::testNameExistsInFile($root.'/'.$testFile, $testName)) {
                                $violations[] = "Item {$item->id}: nama test '{$testName}' tidak ditemukan di dalam '{$testFile}'.";
                            }
                        }
                    }
                }

                // Validate gate: gate report file exists and records test PASS (V4)
                if (! empty($item->proof['gate'])) {
                    foreach ($item->proof['gate'] as $gatePath) {
                        $fullGate = $root.'/'.$gatePath;
                        if (! is_file($fullGate)) {
                            $violations[] = "Item {$item->id}: file laporan gate '{$gatePath}' tidak ditemukan.";

                            continue;
                        }

                        $gateContent = (string) file_get_contents($fullGate);

                        // V4: Test Bukti tercatat LULUS di laporan gate
                        if (! empty($item->proof['test'])) {
                            foreach ($item->proof['test'] as $testEntry) {
                                $parts = explode('::', $testEntry, 2);
                                $testName = $parts[1] ?? null;
                                if ($testName !== null) {
                                    $escapedTest = preg_quote($testName, '/');
                                    if (! preg_match('/`'.$escapedTest.'`.*(?:PASS|🟢)/i', $gateContent) && ! preg_match('/'.$escapedTest.'.*PASS/i', $gateContent)) {
                                        $violations[] = "Item {$item->id}: test '{$testName}' tidak tercatat LULUS di laporan gate '{$gatePath}'.";
                                    }
                                }
                            }
                        }

                        // V4: Tidak ada perubahan di luar docs/ antara commit yang digate dan HEAD
                        if (preg_match('/-\s+\*\*Commit:\*\*\s+`([a-f0-9]+)`/i', $gateContent, $cm)) {
                            $gateCommit = $cm[1];
                            $diffFiles = $inspector->diffFiles($gateCommit, 'HEAD');
                            $nonDocs = array_filter($diffFiles, static fn ($f) => ! str_starts_with($f, 'docs/'));
                            if ($nonDocs !== []) {
                                $sample = implode(', ', array_slice(array_values($nonDocs), 0, 3));
                                $violations[] = "Item {$item->id}: terdapat perubahan di luar docs/ antara commit gate ({$gateCommit}) dan HEAD: {$sample}.";
                            }
                        }
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

    /**
     * Checks if exact test declaration exists in file with prefix normalization.
     * No substring or fuzzy matching allowed.
     */
    public static function testNameExistsInFile(string $filePath, string $declaredTestName): bool
    {
        if (! is_file($filePath)) {
            return false;
        }

        $content = (string) file_get_contents($filePath);

        $candidates = [$declaredTestName];
        if (str_starts_with($declaredTestName, 'it ')) {
            $candidates[] = substr($declaredTestName, 3);
        } elseif (str_starts_with($declaredTestName, 'test ')) {
            $candidates[] = substr($declaredTestName, 5);
        }

        foreach ($candidates as $candidate) {
            // 1. Literal exact quoted string in file (Pest / dataset / description)
            if (str_contains($content, "'{$candidate}'") || str_contains($content, "\"{$candidate}\"")) {
                return true;
            }

            // 2. Exact PHPUnit method declaration
            $snake = 'test_'.str_replace([' ', '-'], '_', strtolower($candidate));
            if (str_contains(strtolower($content), 'function '.$snake.'(')) {
                return true;
            }
        }

        return false;
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
        $routeRoles = [];

        foreach ($middleware as $m) {
            if (str_starts_with($m, 'role:')) {
                $parts = explode(',', substr($m, 5));
                foreach ($parts as $p) {
                    $routeRoles[] = trim($p);
                }
            }
        }

        $routeRoles = array_unique($routeRoles);

        foreach ($expectedRoles as $expected) {
            if (! in_array($expected, $routeRoles, true)) {
                return false;
            }
        }

        return true;
    }

    public static function isCommandRegistered(string $commandSignature): bool
    {
        if (! class_exists(Artisan::class)) {
            return false;
        }

        try {
            $commands = Artisan::all();
            $baseName = explode(' ', trim($commandSignature))[0];

            return isset($commands[$baseName]);
        } catch (Throwable) {
            return false;
        }
    }

    public static function isAuditCommandValid(string $auditName): bool
    {
        $baseName = explode(' ', trim($auditName))[0];
        if (! self::isCommandRegistered($baseName)) {
            return false;
        }

        if (! class_exists(AuditCommandRegistry::class)) {
            return false;
        }

        try {
            $fixtures = AuditCommandRegistry::fixtures();

            return isset($fixtures[$baseName]);
        } catch (Throwable) {
            return false;
        }
    }
}
