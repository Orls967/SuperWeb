<?php

declare(strict_types=1);

namespace Modules\Insurance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Insurance\Application\Services\ReinsuranceAuditor;

final class AuditReinsuranceCommand extends Command
{
    protected $signature = 'ins:reinsurance-audit';

    protected $description = 'Audit dua-sumber reasuransi, kapital, dan cat bond (Fase 157)';

    public function handle(ReinsuranceAuditor $auditor): int
    {
        $this->info('Memulai audit dua-sumber reasuransi & cat bond...');

        $errors = $auditor->audit();

        if ($errors === []) {
            $this->info('Audit reasuransi BERSIH (exit 0). Seluruh invarian terpenuhi.');

            return self::SUCCESS;
        }

        $this->error('Audit reasuransi GAGAL: Terdeteksi inkonsistensi data.');
        foreach ($errors as $err) {
            $this->line(" - {$err}");
        }

        return self::FAILURE;
    }
}

