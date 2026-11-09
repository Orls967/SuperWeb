<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Audit\ChangeRequestAuditValidator;

class ChangeAuditCommand extends Command
{
    protected $signature = 'platform:change-check';

    protected $description = 'Memeriksa audit kelayakan seluruh tiket Change Request aktif terhadap kebijakan ITIL enterprise';

    public function handle(ChangeRequestAuditValidator $validator): int
    {
        $this->info('Memeriksa audit integritas Change Request...');

        $violations = $validator->auditAll();

        if ($violations !== []) {
            $this->error('Ditemukan pelanggaran tata kelola perubahan:');
            foreach ($violations as $v) {
                $this->line("  - {$v}");
            }

            return 1;
        }

        $this->info('Seluruh tiket Change Request patuh terhadap kebijakan tata kelola.');

        return 0;
    }
}
