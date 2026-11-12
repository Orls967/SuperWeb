<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Access\TenantIsolationProbeService;

class AccessTenantProbeCommand extends Command
{
    protected $signature = 'access:tenant-probe {--samples=10 : Jumlah pasangan tenant yang diuji}';

    protected $description = 'Menjalankan probe harian isolasi multi-tenant otomatis atas seluruh model bisnis (W8-D58)';

    public function handle(TenantIsolationProbeService $probeService): int
    {
        $samples = (int) $this->option('samples');
        $this->info("Menjalankan random probe isolasi multi-tenant ({$samples} sampel)...");

        $result = $probeService->runProbe($samples);

        $this->line("Pasangan tenant diuji : {$result['tested_pairs']}");
        $this->line("Model bisnis diperiksa : {$result['checked_models']}");
        $this->line("Kebocoran terdeteksi   : {$result['leaks_detected']}");

        if ($result['status'] === 'FAIL' || $result['leaks_detected'] > 0) {
            $this->error('[TENANT_LEAK_DETECTED] Terdeteksi pelanggaran isolasi tenant!');

            return 1;
        }

        $this->info('[TENANT_PROBE_OK] Seluruh model bisnis terisolasi sempurna (0 kebocoran).');

        return 0;
    }
}
