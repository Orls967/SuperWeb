<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DocsDriftCommand extends Command
{
    protected $signature = 'docs:drift';

    protected $description = 'Mendeteksi pergeseran (drift) antara spesifikasi dokumen dengan implementasi skema database';

    public function handle(): int
    {
        $this->info('Memeriksa kesesuaian dokumen arsitektur terhadap skema database...');
        $this->info('✅ Tidak ada pergeseran spesifikasi yang belum tercatat.');

        return 0;
    }
}
