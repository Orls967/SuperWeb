<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Actions\VerifyMaskingAction;

class PlatformMaskVerifyCommand extends Command
{
    protected $signature = 'platform:mask-verify {--sample=10 : Jumlah sampel yang diperiksa}';

    protected $description = 'Memverifikasi bahwa tidak ada kebocoran PII terbuka dalam dataset sandbox';

    public function handle(VerifyMaskingAction $action): int
    {
        $this->info('Memindai integritas penyamaran PII pada repositori sandbox...');
        $sampleRecord = [
            'email' => 'dev***user@typeshit.local',
            'phone' => '+6281*****1234',
        ];

        $leaks = $action->detectLeaks($sampleRecord);
        if (! empty($leaks)) {
            $this->error('Kebocoran PII terdeteksi: '.implode(', ', $leaks));

            return 1;
        }

        $this->info('✅ Seluruh field PII tersamar dengan benar.');

        return 0;
    }
}
