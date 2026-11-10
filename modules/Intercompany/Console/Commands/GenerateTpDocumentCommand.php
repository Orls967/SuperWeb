<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Intercompany\Application\Actions\GenerateTpDocumentationAction;
use Psr\Clock\ClockInterface;

class GenerateTpDocumentCommand extends Command
{
    protected $signature = 'ic:generate-tp-doc {--entity=HOLDING : Kode entitas wajib pajak} {--fiscal-year= : Tahun pajak YYYY} {--type=local_file : Jenis dokumen: master_file, local_file, cbcr}';

    protected $description = 'Menghasilkan dokumen kepatuhan Transfer Pricing (Master File / Local File / CbCR) sesuai PMK-172';

    public function handle(
        GenerateTpDocumentationAction $action,
        ?ClockInterface $clock = null
    ): int {
        $now = $clock !== null ? CarbonImmutable::instance($clock->now()) : CarbonImmutable::now();
        $fiscalYear = (int) ($this->option('fiscal-year') ?: $now->year);
        $entity = (string) $this->option('entity');
        $type = (string) $this->option('type');

        $this->info("Menghasilkan Dokumen TP ({$type}) untuk {$entity} Tahun Pajak {$fiscalYear}...");

        $doc = $action->execute([
            'entity_code' => $entity,
            'fiscal_year' => $fiscalYear,
            'document_type' => $type,
            'operator_id' => null,
        ]);

        $this->info('Dokumen TP berhasil diterbitkan!');
        $this->line("Nomor Dokumen: {$doc->document_number}");
        $this->line("Tipe Dokumen: {$doc->document_type}");
        $this->line("SHA-256 Hash Integritas: {$doc->document_hash}");
        $this->line("Status: {$doc->status}");

        return self::SUCCESS;
    }
}
