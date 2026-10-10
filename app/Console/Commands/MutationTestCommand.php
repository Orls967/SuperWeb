<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Mutation testing runner using Pest --mutate (PROGRESS R0.12, KONSEP §A14.8, K-27).
 *
 * Runs mutation testing on touched Action/Service classes with a minimum threshold of 60%.
 */
class MutationTestCommand extends Command
{
    protected $signature = 'test:mutate
        {--class=* : Spesifikasikan nama kelas target mutasi (mis. Modules\\Hospital\\Application\\Actions\\AdmitPatientAction)}
        {--min=60 : Ambang batas skor mutasi minimum dalam persen (bawaan: 60)}
        {--git-diff : Deteksi otomatis kelas Action/Service yang disentuh git branch terhadap origin/main}
        {--base=main : Base branch untuk git diff (bawaan: main)}
        {--bail : Hentikan eksekusi pada mutasi pertama yang gagal}
        {--dry-run : Tampilkan perintah pest yang akan dijalankan tanpa mengeksekusi}';

    protected $description = 'Jalankan mutation testing Pest (--mutate) dengan ambang skor minimum 60%';

    public function handle(): int
    {
        $minScore = (int) $this->option('min');
        $classes = (array) $this->option('class');

        if ($this->option('git-diff')) {
            $base = (string) $this->option('base');
            $detected = $this->detectChangedClasses($base);
            $classes = array_values(array_unique([...$classes, ...$detected]));
        }

        $cmdParts = [
            'vendor/bin/pest',
            '--mutate',
            "--min={$minScore}",
            '--ignore-min-score-on-zero-mutations',
        ];

        if ($this->option('bail')) {
            $cmdParts[] = '--bail';
        }

        if ($classes !== []) {
            $classArg = implode(',', $classes);
            $cmdParts[] = "--class={$classArg}";
        } else {
            $cmdParts[] = '--covered-only';
        }

        $fullCmd = implode(' ', $cmdParts);

        if ($this->option('dry-run')) {
            $this->info("Perintah mutasi yang disiapkan:\n{$fullCmd}");

            return self::SUCCESS;
        }

        $this->info("Menjalankan Pest Mutation Testing (ambang skor minimum: {$minScore}%)...");
        $this->line("Perintah: {$fullCmd}");

        passthru($fullCmd, $exitCode);

        return $exitCode;
    }

    /**
     * @return list<string>
     */
    private function detectChangedClasses(string $baseBranch): array
    {
        $output = shell_exec("git diff --name-only origin/{$baseBranch}...HEAD 2>/dev/null || git diff --name-only {$baseBranch}...HEAD 2>/dev/null || git diff --name-only HEAD~1 2>/dev/null");
        if ($output === null || trim($output) === '') {
            return [];
        }

        $files = array_filter(array_map('trim', explode("\n", trim($output))));
        $classes = [];

        foreach ($files as $file) {
            if (! str_ends_with($file, '.php')) {
                continue;
            }
            if (! str_contains($file, 'Application/Actions/') && ! str_contains($file, 'Application/Services/') && ! str_contains($file, 'app/Quality/')) {
                continue;
            }

            // Convert path to namespace
            $clean = substr($file, 0, -4);
            $fqcn = str_replace('/', '\\', $clean);
            if (str_starts_with($fqcn, 'modules\\')) {
                $fqcn = 'Modules\\'.substr($fqcn, 8);
            } elseif (str_starts_with($fqcn, 'app\\')) {
                $fqcn = 'App\\'.substr($fqcn, 4);
            }

            $classes[] = $fqcn;
        }

        return array_values(array_unique($classes));
    }
}
