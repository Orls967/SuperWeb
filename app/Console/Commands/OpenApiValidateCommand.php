<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\OpenApi\OpenApiContractValidator;
use Illuminate\Console\Command;

final class OpenApiValidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openapi:validate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validasi spesifikasi OpenAPI 3.1.0 dengan rute aktual, cakupan 46 modul, dan aturan arsitektur';

    public function handle(OpenApiContractValidator $validator): int
    {
        $this->info('Memvalidasi kontrak spesifikasi OpenAPI 3.1.0 terhadap rute aktual dan aturan arsitektur...');

        $result = $validator->validate();

        if ($result->isValid()) {
            $stats = $result->stats();
            $this->info('✅ Spesifikasi OpenAPI 3.1.0 VALID dan 100% SINKRON dengan rute aplikasi!');

            $this->table(
                ['Parameter Kontrak', 'Nilai / Status'],
                [
                    ['OpenAPI Version', '3.1.0 🟢'],
                    ['Total Paths Terdaftar', ($stats['total_paths'] ?? 0).' 🟢'],
                    ['Total Operasi Tervalidasi', ($stats['total_operations'] ?? 0).' 🟢'],
                    ['Tags Modul Kanonikal', ($stats['canonical_modules_covered'] ?? 0).' / 46 Modul 🟢'],
                    ['Rute API (api/*) Diuji', ($stats['api_routes_checked'] ?? 0).' 🟢'],
                    ['MoneyMinorUnit Invariant', 'integer / int64 (Bebas IEEE 754 float) 🟢'],
                    ['Header Idempotensi & Tracing', 'Idempotency-Key & X-Correlation-Id 🟢'],
                    ['Skema Error Standar', 'RFC 7807 Problem Details 🟢'],
                ]
            );

            foreach ($result->warnings() as $warning) {
                $this->warn("⚠️  {$warning}");
            }

            return self::SUCCESS;
        }

        $this->error('❌ Validasi kontrak OpenAPI 3.1.0 GAGAL:');
        foreach ($result->errors() as $index => $err) {
            $num = $index + 1;
            $this->line("  <fg=red>{$num}.</> {$err}");
        }

        $this->newLine();
        $this->line('Jalankan: <fg=yellow>php artisan openapi:generate</> untuk meregenerasi spesifikasi.');

        return self::FAILURE;
    }
}
