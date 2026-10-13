<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Mutation\MutationTargetResolver;
use Illuminate\Console\Command;

/**
 * Mutation testing runner using Pest --mutate (PROGRESS R0.12, KONSEP §A14.8, K-MUT).
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

        if ($classes === []) {
            $this->info('tidak ada kelas Action/Service berubah');

            return self::SUCCESS;
        }

        $overallExitCode = self::SUCCESS;
        $commands = [];

        foreach ($classes as $class) {
            $testPath = MutationTargetResolver::resolveTestPathForClass($class, base_path());
            $cmdParts = [
                'vendor/bin/pest',
            ];
            if ($testPath !== null && $testPath !== '') {
                $cmdParts[] = $testPath;
            }
            $cmdParts[] = '--mutate';
            $cmdParts[] = "--min={$minScore}";
            $cmdParts[] = "--class='{$class}'";

            if ($this->option('bail')) {
                $cmdParts[] = '--bail';
            }

            $cmd = implode(' ', $cmdParts);
            $commands[] = [
                'class' => $class,
                'cmd' => $cmd,
            ];
        }

        if ($this->option('dry-run')) {
            $this->info('Daftar kelas target mutasi ('.count($classes).'):');
            foreach ($commands as $item) {
                $this->line("- Kelas: {$item['class']}");
                $this->line("  Perintah: {$item['cmd']}");
            }

            return self::SUCCESS;
        }

        $this->info("Menjalankan Pest Mutation Testing per kelas (ambang batas: {$minScore}%)...");
        foreach ($commands as $item) {
            $this->line("==> Menjalankan mutasi untuk: {$item['class']}");
            $this->line("Perintah: {$item['cmd']}");
            passthru($item['cmd'], $code);

            if ($code !== 0) {
                $overallExitCode = $code;
                if ($this->option('bail')) {
                    break;
                }
            }
        }

        return $overallExitCode;
    }

    /**
     * @return list<string>|null Null indicates git error (merge-base / diff failure)
     */
    private function detectChangedClasses(string $baseBranch): ?array
    {
        $cleanBase = trim($baseBranch);
        $mergeBaseOutput = [];
        $mergeBaseCode = 1;

        exec("git merge-base {$cleanBase} HEAD 2>/dev/null || git merge-base origin/{$cleanBase} HEAD 2>/dev/null", $mergeBaseOutput, $mergeBaseCode);
        $mergeBase = trim(implode("\n", $mergeBaseOutput));

        if ($mergeBaseCode !== 0 || $mergeBase === '') {
            $diffOutput = [];
            $diffCode = 1;
            exec("git diff --name-only {$cleanBase}...HEAD 2>/dev/null || git diff --name-only origin/{$cleanBase}...HEAD 2>/dev/null", $diffOutput, $diffCode);

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
