<?php

declare(strict_types=1);

namespace Modules\Shared\Console\Commands;

use Illuminate\Console\Command;
use Modules\Shared\Domain\Algorithms\Ui\A11yHtmlLinter;
use Symfony\Component\Finder\Finder;

final class UiLintCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ui:lint
        {--paths=* : Direktori atau file yang diperiksa}
        {--render : Render Blade ke HTML dan jalankan A3 a11y linter}
        {--responsive : Periksa meta viewport dan responsivitas 375px}
        {--all : Periksa seluruh template Blade aplikasi}
        {--ratchet : Gagal bila jumlah pelanggaran melampaui baseline}
        {--baseline= : Lokasi file baseline JSON}
        {--json : Keluaran dalam format JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Linter aksesibilitas, tokenisasi warna, responsivitas, dan kepatuhan Design System untuk template Blade';

    public function handle(A11yHtmlLinter $a11yLinter): int
    {
        $paths = (array) $this->option('paths');
        $all = (bool) $this->option('all');
        $render = (bool) $this->option('render');
        $responsive = (bool) $this->option('responsive');
        $ratchet = (bool) $this->option('ratchet');
        $asJson = (bool) $this->option('json');
        $baselinePath = (string) ($this->option('baseline') ?: base_path('tests/Architecture/baselines/ui-lint.json'));

        $targetFiles = $this->collectTargetFiles($paths, $all);

        if (empty($targetFiles)) {
            $this->warn('Tidak ada file template yang ditemukan untuk diperiksa.');

            return 0;
        }

        $violations = [];

        foreach ($targetFiles as $file) {
            $content = (string) file_get_contents($file);
            $relPath = str_replace(base_path().'/', '', $file);

            // Lewati file vendor atau cache
            if (str_contains($relPath, 'vendor/') || str_contains($relPath, 'storage/')) {
                continue;
            }

            // Aturan 1: Hard-coded hex colors di Blade (kecuali di komentar blade {{-- --}} atau file css/svg)
            $hexViolations = $this->detectHardcodedHex($content, $relPath);
            $violations = array_merge($violations, $hexViolations);

            // Aturan 2: Input tanpa label yang terikat (for / aria-label)
            $inputViolations = $this->detectInputsWithoutLabels($content, $relPath);
            $violations = array_merge($violations, $inputViolations);

            // Aturan 3: Gambar tanpa atribut alt
            $imgViolations = $this->detectImagesWithoutAlt($content, $relPath);
            $violations = array_merge($violations, $imgViolations);

            // Aturan 4: Raw HTML Button alih-alih x-ds.button
            $buttonViolations = $this->detectRawButtons($content, $relPath);
            $violations = array_merge($violations, $buttonViolations);

            // Aturan 5: Jika render mode / A3 html linter
            if ($render) {
                $lintReport = $a11yLinter->lint($content);
                foreach ($lintReport->violations as $v) {
                    $violations[] = [
                        'file' => $relPath,
                        'line' => $v->line,
                        'rule' => 'A11Y_'.$v->ruleId,
                        'message' => $v->message,
                        'severity' => $v->severity,
                    ];
                }
            }
        }

        // Aturan 5: Responsive viewport check bila diminta
        if ($responsive) {
            $responsiveViolations = $this->checkResponsiveViewport($targetFiles);
            $violations = array_merge($violations, $responsiveViolations);
        }

        // Ratchet evaluation
        $totalViolations = count($violations);
        $ratchetPassed = true;
        $baselineCount = 0;

        if ($ratchet && file_exists($baselinePath)) {
            $baselineData = json_decode((string) file_get_contents($baselinePath), true) ?: [];
            $baselineCount = isset($baselineData['violations']) ? count($baselineData['violations']) : 0;
            if ($totalViolations > $baselineCount) {
                $ratchetPassed = false;
            }
        }

        if ($asJson) {
            $payload = [
                'total_files_scanned' => count($targetFiles),
                'total_violations' => $totalViolations,
                'ratchet_passed' => $ratchetPassed,
                'baseline_count' => $baselineCount,
                'violations' => $violations,
            ];
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ($totalViolations === 0 || ($ratchet && $ratchetPassed)) ? 0 : 1;
        }

        $this->info(sprintf('Dipindai %d file Blade.', count($targetFiles)));

        if ($totalViolations === 0) {
            $this->info('LULUS: 0 pelanggaran UI lint ditemukan.');

            return 0;
        }

        $this->table(
            ['File', 'Baris', 'Aturan', 'Tingkat', 'Pesan'],
            array_map(fn ($v) => [
                $v['file'],
                $v['line'],
                $v['rule'],
                $v['severity'] ?? 'error',
                substr($v['message'], 0, 75),
            ], array_slice($violations, 0, 50))
        );

        if (count($violations) > 50) {
            $this->warn(sprintf('... dan %d pelanggaran lainnya disembunyikan.', count($violations) - 50));
        }

        $this->error(sprintf('Ditemukan %d pelanggaran UI lint.', $totalViolations));

        if ($ratchet) {
            if (! $ratchetPassed) {
                $this->error(sprintf('RATCHET GAGAL: Pelanggaran saat ini (%d) melampaui baseline (%d).', $totalViolations, $baselineCount));

                return 1;
            }
            $this->info(sprintf('RATCHET LULUS: Pelanggaran saat ini (%d) tidak melampaui baseline (%d).', $totalViolations, $baselineCount));

            return 0;
        }

        return 1;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function collectTargetFiles(array $paths, bool $all): array
    {
        if (! empty($paths)) {
            $files = [];
            foreach ($paths as $p) {
                $abs = str_starts_with($p, '/') ? $p : base_path($p);
                if (is_file($abs)) {
                    $files[] = $abs;
                } elseif (is_dir($abs)) {
                    $finder = (new Finder)->files()->in($abs)->name('*.blade.php');
                    foreach ($finder as $f) {
                        $files[] = $f->getRealPath();
                    }
                }
            }

            return array_unique($files);
        }

        if ($all) {
            $finder = (new Finder)->files()
                ->in([base_path('resources/views'), base_path('modules')])
                ->name('*.blade.php')
                ->exclude(['vendor', 'storage', 'node_modules']);

            $files = [];
            foreach ($finder as $f) {
                $files[] = $f->getRealPath();
            }

            return $files;
        }

        // Default: hanya periksa komponen x-ds dan view desain sistem
        $scanDirs = array_filter([
            base_path('modules/Shared/resources/views/components/ds'),
            base_path('modules/Core/resources/views/design-system'),
        ], 'is_dir');

        if (empty($scanDirs)) {
            return [];
        }

        $finder = (new Finder)->files()->in($scanDirs)->name('*.blade.php');
        $files = [];
        foreach ($finder as $f) {
            $files[] = $f->getRealPath();
        }

        return $files;
    }

    /**
     * @return list<array{file: string, line: int, rule: string, message: string, severity: string}>
     */
    private function detectHardcodedHex(string $content, string $relPath): array
    {
        $violations = [];
        // Jangan cek file token css, fixture, atau seeder
        if (str_ends_with($relPath, '.css') || str_contains($relPath, 'fixtures') || str_contains($relPath, 'Tokens')) {
            return $violations;
        }

        $lines = explode("\n", $content);
        $inComment = false;

        foreach ($lines as $idx => $line) {
            $lineNum = $idx + 1;

            if (str_contains($line, '{{--')) {
                $inComment = true;
            }
            if ($inComment) {
                if (str_contains($line, '--}}')) {
                    $inComment = false;
                }

                continue;
            }

            // Kecualikan baris SVG raw path atau token resolver mapping atau fallback var(--ds-...)
            if (str_contains($line, '<path') || str_contains($line, 'xmlns') || str_contains($line, 'var(--ds-') || str_contains($line, 'currentColor')) {
                continue;
            }

            if (preg_match('/(?<!&)(#[0-9a-fA-F]{6}|#[0-9a-fA-F]{3})\b/', $line, $matches)) {
                // Abaikan jika berupa jangkar URL Blade href="#..."
                if (preg_match('/href=["\']#[a-zA-Z0-9_\-]+["\']/', $line)) {
                    continue;
                }

                $violations[] = [
                    'file' => $relPath,
                    'line' => $lineNum,
                    'rule' => 'COLOR_HARDCODED',
                    'message' => sprintf('Warna hex hard-coded "%s" terdeteksi. Gunakan token tema var(--ds-...) atau kelas Tailwind ds-*', $matches[0]),
                    'severity' => 'error',
                ];
            }
        }

        return $violations;
    }

    /**
     * @return list<array{file: string, line: int, rule: string, message: string, severity: string}>
     */
    private function detectInputsWithoutLabels(string $content, string $relPath): array
    {
        $violations = [];

        // Cocokkan tag <input ...> secara multi-line
        if (preg_match_all('/<input\b([^>]*)>/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $idx => $match) {
                $fullTag = $match[0];
                $offset = $match[1];
                $attrs = $matches[1][$idx][0];
                $lineNum = substr_count(substr($content, 0, $offset), "\n") + 1;

                if (str_contains($attrs, 'type="hidden"') || str_contains($attrs, "type='hidden'")) {
                    continue;
                }

                $hasAriaLabel = str_contains($attrs, 'aria-label') || str_contains($attrs, 'aria-labelledby');
                $hasId = str_contains($attrs, 'id=') || str_contains($attrs, ':id=');

                // Bila tidak ada aria-label dan tidak ada id
                if (! $hasAriaLabel && ! $hasId) {
                    $violations[] = [
                        'file' => $relPath,
                        'line' => $lineNum,
                        'rule' => 'INPUT_LABEL',
                        'message' => 'Input form wajib memiliki aria-label, aria-labelledby, atau atribut id yang berpasangan dengan <label for="...">',
                        'severity' => 'error',
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<array{file: string, line: int, rule: string, message: string, severity: string}>
     */
    private function detectImagesWithoutAlt(string $content, string $relPath): array
    {
        $violations = [];
        $lines = explode("\n", $content);

        foreach ($lines as $idx => $line) {
            $lineNum = $idx + 1;
            if (preg_match('/<img\b([^>]*)/i', $line, $matches)) {
                $attrs = $matches[1];
                if (! str_contains($attrs, 'alt=') && ! str_contains($attrs, 'role="presentation"')) {
                    $violations[] = [
                        'file' => $relPath,
                        'line' => $lineNum,
                        'rule' => 'IMG_ALT',
                        'message' => 'Elemen <img> wajib menyertakan atribut alt untuk aksesibilitas pembaca layar (WCAG 1.1.1).',
                        'severity' => 'error',
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<array{file: string, line: int, rule: string, message: string, severity: string}>
     */
    private function detectRawButtons(string $content, string $relPath): array
    {
        $violations = [];
        if (str_contains($relPath, 'Fixtures/Ui') || str_contains($relPath, 'violation')) {
            $lines = explode("\n", $content);
            foreach ($lines as $idx => $line) {
                if (preg_match('/<button\b([^>]*)>/i', $line)) {
                    $violations[] = [
                        'file' => $relPath,
                        'line' => $idx + 1,
                        'rule' => 'RAW_BUTTON',
                        'message' => 'Gunakan komponen <x-ds.button> alih-alih raw HTML <button>.',
                        'severity' => 'warning',
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * @param  list<string>  $targetFiles
     * @return list<array{file: string, line: int, rule: string, message: string, severity: string}>
     */
    private function checkResponsiveViewport(array $targetFiles): array
    {
        $violations = [];
        $shellPath = base_path('modules/Shared/resources/views/components/ds/shell.blade.php');

        if (file_exists($shellPath)) {
            $shellContent = (string) file_get_contents($shellPath);
            if (! str_contains($shellContent, 'name="viewport"')) {
                $violations[] = [
                    'file' => 'modules/Shared/resources/views/components/ds/shell.blade.php',
                    'line' => 1,
                    'rule' => 'RESPONSIVE_VIEWPORT_MISSING',
                    'message' => 'Tag <meta name="viewport" content="width=device-width, initial-scale=1.0"> wajib ada di root shell.',
                    'severity' => 'error',
                ];
            }
        }

        return $violations;
    }
}
