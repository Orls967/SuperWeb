<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Mutation\MutationTargetResolver;
use Illuminate\Console\Command;

/**
 * Mutation testing runner using Pest --mutate (PROGRESS R0.12, KONSEP §A14.8, K-27).
 *
 * Runs mutation testing on touched Action/Service classes with a minimum threshold of 60%.
 * Note: --covered-only is forbidden in CI as it artificially inflates scores.
 */
class MutationTestCommand extends Command
{
    protected $signature = 'test:mutate
        {--class=* : Spesifikasikan nama kelas target mutasi (mis. Modules\\Hospital\\Application\\Actions\\AdmitPatientAction)}
        {--min=60 : Ambang batas skor mutasi minimum dalam persen (bawaan: 60)}
        {--git-diff : Deteksi otomatis kelas Action/Service yang disentuh git branch terhadap origin/master}
        {--base=master : Base branch untuk git diff (bawaan: master)}
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

            if ($detected === null) {
                $this->error("Gagal menjalankan git merge-base/diff terhadap base branch '{$base}'.");

                return self::FAILURE;
            }

            $classes = array_values(array_unique([...$classes, ...$detected]));
        }

        if ($classes === [] && $this->option('git-diff')) {
            $this->info('Tidak ada kelas Action/Service yang disentuh pada branch ini. Mutation testing dilewati.');

            return self::SUCCESS;
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
     * @return list<string>|null Null indicates git error (merge-base / diff failure)
     */
    private function detectChangedClasses(string $baseBranch): ?array
    {
        $mergeBaseOutput = [];
        $mergeBaseCode = 1;
        exec("git merge-base origin/{$baseBranch} HEAD 2>/dev/null || git merge-base {$baseBranch} HEAD 2>/dev/null", $mergeBaseOutput, $mergeBaseCode);
        $mergeBase = trim(implode("\n", $mergeBaseOutput));

        if ($mergeBaseCode !== 0 || $mergeBase === '') {
            $diffOutput = [];
            $diffCode = 1;
            exec("git diff --name-only origin/{$baseBranch}...HEAD 2>/dev/null || git diff --name-only {$baseBranch}...HEAD 2>/dev/null", $diffOutput, $diffCode);

            if ($diffCode !== 0) {
                return null;
            }

            $files = array_filter(array_map('trim', $diffOutput));

            return MutationTargetResolver::resolveClasses($files);
        }

        $diffOutput = [];
        $diffCode = 1;
        exec("git diff --name-only {$mergeBase} HEAD 2>/dev/null", $diffOutput, $diffCode);

        if ($diffCode !== 0) {
            return null;
        }

        $files = array_filter(array_map('trim', $diffOutput));

        return MutationTargetResolver::resolveClasses($files);
    }
}
