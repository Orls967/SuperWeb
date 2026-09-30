<?php

declare(strict_types=1);

namespace Modules\Mall\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mall\Application\Queries\AuditBillingQuery;

class AuditBillingCommand extends Command
{
    protected $signature = 'mall:audit-billing';

    protected $description = 'Audit kesesuaian dan integritas seluruh invoice mall terhadap buku besar double-entry ledger';

    public function handle(AuditBillingQuery $query): int
    {
        $this->info('Memulai audit integritas penagihan mall vs pembukuan double-entry ledger...');

        $result = $query->execute();

        $this->table(
            ['Metrik Audit', 'Nilai'],
            [
                ['Jumlah Invoice Diperiksa', $result['invoices_checked']],
                ['Total Tagihan Diterbitkan', 'Rp '.number_format($result['total_billed'], 0, ',', '.')],
                ['Total Penerimaan Invoice', 'Rp '.number_format($result['total_paid'], 0, ',', '.')],
                ['Total Kredit Ledger Pendapatan', 'Rp '.number_format($result['ledger_paid'], 0, ',', '.')],
                ['Status Audit', $result['passed'] ? '<fg=green>SEIMBANG & LOLOS (0 Selisih)</>' : '<fg=red>DITEMUKAN DISKREPANSI</>'],
            ]
        );

        if (! $result['passed']) {
            $this->error('Audit Billing GAGAL! Ditemukan selisih data:');
            foreach ($result['discrepancies'] as $err) {
                $this->error(" - {$err}");
            }

            return self::FAILURE;
        }

        $this->info('✓ [OK] Seluruh invoice mall telah terverifikasi sinkron sempurna dengan pembukuan double-entry ledger.');

        return self::SUCCESS;
    }
}
