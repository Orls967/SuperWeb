<?php

declare(strict_types=1);

namespace Modules\Logistics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Logistics\Application\Services\BillingAuditor;

class AuditBillingCommand extends Command
{
    protected $signature = 'lgx:audit-billing';

    protected $description = 'Audit penagihan logistik: invoice freight, D&D, bea cukai, COD, carrier, klaim, BBM, dan pendapatan Delivered vs unearned terhadap buku besar';

    public function handle(BillingAuditor $auditor): int
    {
        $this->info('Memulai audit penagihan logistik terhadap buku besar...');

        $results = $auditor->run();

        $this->table(
            ['Pemeriksaan', 'Item', 'Dokumen (Rp)', 'Ledger (Rp)', 'Status'],
            array_map(fn ($r) => [
                $r['label'],
                $r['items'],
                number_format($r['document'], 0, ',', '.'),
                number_format($r['ledger'], 0, ',', '.'),
                $r['ok'] ? 'OK' : 'SELISIH',
            ], $results)
        );

        foreach ($results as $r) {
            if (! $r['ok']) {
                $this->error("✗ {$r['label']}: {$r['detail']}");
            }
        }

        if (! $auditor->passed($results)) {
            return self::FAILURE;
        }

        $checked = array_sum(array_column($results, 'items'));
        $this->info("✓ Seluruh {$checked} dokumen logistik sinkron dengan buku besar (0 selisih).");

        return self::SUCCESS;
    }
}
