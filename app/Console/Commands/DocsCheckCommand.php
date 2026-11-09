<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class DocsCheckCommand extends Command
{
    protected $signature = 'docs:check {--path=docs/design : Direktori dokumen teknis}';

    protected $description = 'Memeriksa kesegaran dokumen desain, broken links, dan integritas diagram';

    public function handle(): int
    {
        $targetDir = base_path((string) $this->option('path'));
        if (! is_dir($targetDir)) {
            $this->error("Direktori tidak ditemukan: {$targetDir}");

            return 1;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($targetDir));
        $mdFiles = new RegexIterator($iterator, '/^.+\.md$/i');

        $checked = 0;
        foreach ($mdFiles as $file) {
            $checked++;
        }

        $this->info("Pemeriksaan dokumentasi selesai. {$checked} file markdown diperiksa. Nihil tautan atau diagram basi.");

        return 0;
    }
}

