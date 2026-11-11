<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Access\CoreAccessAuditor;

class AccessAuditCommand extends Command
{
    protected $signature = 'access:audit';

    protected $description = 'Menjalankan audit dua-sumber atas integritas tata kelola akses dan riwayat transisi imutabel (W8-D60)';

    public function handle(CoreAccessAuditor $auditor): int
    {
        $this->info('Memulai audit dua-sumber tata kelola akses & identitas...');

        $result = $auditor->audit();

        $this->line("Total grant diaudit  : {$result['audited_grants']}");
        $this->line("Total link diaudit   : {$result['audited_links']}");
        $this->line('Total diskrepansi    : '.count($result['discrepancies']));

        if ($result['status'] !== 0) {
            $this->error('[ACCESS_AUDIT_FAIL] Terdeteksi anomali pada integritas tata kelola akses:');
            foreach ($result['discrepancies'] as $disc) {
                $this->error(" - {$disc}");
            }

            return 1;
        }

        $this->info('[ACCESS_AUDIT_OK] Seluruh hak akses dan tautan identitas konsisten 100% dengan log imutabel.');

        return 0;
    }
}
