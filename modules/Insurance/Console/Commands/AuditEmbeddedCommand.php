<?php

declare(strict_types=1);

namespace Modules\Insurance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Insurance\Application\Services\EmbeddedAuditor;

final class AuditEmbeddedCommand extends Command
{
    protected $signature = 'ins:embedded-audit';

    protected $description = 'Audit dua-sumber embedded insurance, komisi broker, trigger parametrik, dan investigasi SIU (Fase 158)';

    public function handle(EmbeddedAuditor $auditor): int
    {
        $this->info('Memulai audit dua-sumber embedded insurance & broker marketplace...');

        $result = $auditor->audit();

        if (! $result['clean']) {
            $this->error('Audit embedded GAGAL! Ditemukan pelanggaran invarian:');
            foreach ($result['violations'] as $v) {
                $this->line(" - {$v}");
            }

            return self::FAILURE;
        }

        $this->info('Audit embedded BERSIH (exit 0). Seluruh invarian terpenuhi.');
        $this->table(['Metrik', 'Jumlah'], [
            ['Polis Embedded', $result['stats']['policies_audited']],
            ['Komisi Broker', $result['stats']['commissions_audited']],
            ['Event Parametrik', $result['stats']['events_audited']],
            ['Kasus SIU', $result['stats']['siu_cases_audited']],
        ]);

        return self::SUCCESS;
    }
}
