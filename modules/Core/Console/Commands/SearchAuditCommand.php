<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Core\Domain\Models\CoreSearchTerm;
use Modules\Core\Domain\Models\CoreTask;

class SearchAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:audit {--verify-dual-source : Jalankan verifikasi integritas dua sumber (primary DB vs logs/histories)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Melakukan audit integritas dua sumber pada indeks pencarian dan kotak masuk tugas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Memulai Audit Integritas Dua Sumber (Search & Inbox)...');

        $isCorrupted = false;
        $corruptions = [];

        // 1. Audit Dangling Terms tanpa Dokumen Indeks
        $danglingCount = CoreSearchTerm::query()
            ->whereNotIn('search_index_id', CoreSearchIndex::query()->select('id'))
            ->count();

        if ($danglingCount > 0) {
            $isCorrupted = true;
            $corruptions[] = sprintf('Ditemukan %d entri posting term yatim piatu (dangling) tanpa dokumen indeks induk.', $danglingCount);
        }

        // 2. Audit Integritas Mutasi Tugas vs Riwayat Histori
        $tasks = CoreTask::query()->with(['histories' => fn ($q) => $q->orderBy('created_at', 'desc')->orderBy('id', 'desc')])->get();

        foreach ($tasks as $task) {
            $latestHistory = $task->histories->first();

            // Jika ada riwayat, status tugas harus selaras dengan to_status terakhir
            if ($latestHistory !== null && $latestHistory->to_status !== $task->status) {
                $isCorrupted = true;
                $corruptions[] = sprintf(
                    'Inkonsistensi integritas Task [%s]: status aktif [%s] berbeda dengan riwayat mutasi terakhir [%s].',
                    $task->id,
                    $task->status,
                    $latestHistory->to_status
                );
            }
        }

        if ($isCorrupted) {
            $this->error('=============================================');
            $this->error('INTEGRITY STATUS: CORRUPTED');
            $this->error('=============================================');
            foreach ($corruptions as $err) {
                $this->line(' - '.$err);
            }

            return 1;
        }

        $this->info('=============================================');
        $this->info('INTEGRITY STATUS: CLEAN');
        $this->info('=============================================');
        $this->line('Seluruh indeks pencarian, posting list, dan status siklus tugas terverifikasi koheren.');

        return 0;
    }
}
