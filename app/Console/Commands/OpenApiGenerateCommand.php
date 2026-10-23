<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\OpenApi\OpenApiSpecGenerator;
use Illuminate\Console\Command;

final class OpenApiGenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openapi:generate
        {--check : Periksa apakah file spesifikasi OpenAPI sinkron dengan rute aplikasi tanpa menulis ulang}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate dokumen spesifikasi OpenAPI 3.1.0 (JSON, YAML, Interactive Explorer) lintas seluruh 46 modul';

    public function handle(OpenApiSpecGenerator $generator): int
    {
        if ($this->option('check')) {
            $this->info('Memeriksa sinkronisasi spesifikasi OpenAPI 3.1.0...');
            $isSynced = $generator->isSynchronized();

            if ($isSynced) {
                $this->info('✅ docs/openapi-v3.json sinkron 100% dengan rute aplikasi saat ini.');

                return self::SUCCESS;
            }

            $this->error('❌ docs/openapi-v3.json TIDAK SINKRON dengan rute aplikasi!');
            $this->line('Jalankan: php artisan openapi:generate untuk memperbarui dokumen spesifikasi.');

            return self::FAILURE;
        }

        $this->info('Menginspeksi seluruh rute aplikasi, peran RBAC, dan modul...');
        $written = $generator->writeFiles();

        $spec = $generator->buildSpec();
        $pathCount = count($spec['paths'] ?? []);
        $tagCount = count($spec['tags'] ?? []);
        $schemaCount = count($spec['components']['schemas'] ?? []);

        $this->info('✅ Berhasil men-generate spesifikasi OpenAPI 3.1.0:');
        $this->table(
            ['Artefak', 'Lokasi File', 'Ukuran'],
            [
                ['OpenAPI 3.1.0 JSON', $written['json'], number_format(filesize($written['json'])).' bytes'],
                ['OpenAPI 3.1.0 YAML', $written['yaml'], number_format(filesize($written['yaml'])).' bytes'],
                ['Interactive API Explorer', $written['explorer'], number_format(filesize($written['explorer'])).' bytes'],
            ]
        );

        $this->line("Statistik: <fg=green>{$pathCount}</> paths, <fg=green>{$tagCount}</> module tags, <fg=green>{$schemaCount}</> schemas (MoneyMinorUnit: integer/int64).");

        return self::SUCCESS;
    }
}
