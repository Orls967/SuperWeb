<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Inventory\CodebaseInventoryGenerator;
use Illuminate\Console\Command;

/**
 * Command untuk menghasilkan dan memverifikasi bagian faktual docs/CODEBASE.md (K-03, PROGRESS R5.1).
 */
class DocsInventoryCommand extends Command
{
    protected $signature = 'docs:inventory
        {--write : Tulis atau perbarui bagian faktual ke docs/CODEBASE.md}
        {--check : Periksa apakah docs/CODEBASE.md sinkron dengan kondisi codebase saat ini}';

    protected $description = 'Generate bagian faktual docs/CODEBASE.md (modul, prefiks, migrasi, rute, command & jadwal, akun ledger) (K-03, PROGRESS R5.1)';

    public function handle(CodebaseInventoryGenerator $generator): int
    {
        $codebasePath = base_path('docs/CODEBASE.md');

        if ($this->option('write')) {
            $this->info('Menulis bagian faktual ke docs/CODEBASE.md...');
            $written = $generator->writeToCodebase($codebasePath);

            if ($written) {
                $this->info('✅ Berhasil menyinkronkan docs/CODEBASE.md dengan inventaris faktual codebase.');

                return self::SUCCESS;
            }

            $this->error('❌ Gagal menulis ke docs/CODEBASE.md.');

            return self::FAILURE;
        }

        $this->info('Memeriksa sinkronisasi bagian faktual docs/CODEBASE.md...');
        $isSynced = $generator->isSynchronized($codebasePath);

        if ($isSynced) {
            $this->info('✅ docs/CODEBASE.md sinkron 100% dengan kondisi faktual codebase.');

            return self::SUCCESS;
        }

        $this->error('❌ docs/CODEBASE.md TIDAK SINKRON dengan kondisi faktual codebase!');
        $this->line('Jalankan: php artisan docs:inventory --write untuk menyinkronkan.');

        return self::FAILURE;
    }
}
