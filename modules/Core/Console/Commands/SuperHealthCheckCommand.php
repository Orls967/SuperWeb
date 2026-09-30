<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Services\SystemHealthService;

class SuperHealthCheckCommand extends Command
{
    protected $signature = 'super:health-check';

    protected $description = 'Jalankan diagnosa komprehensif kesehatan seluruh ekosistem platform (DB, Cache, Storage, Ledger, Passport, Mall, Resto)';

    public function handle(SystemHealthService $healthService): int
    {
        $this->info('================================================================');
        $this->info('  SUPERWEBSITE SYSTEM OBSERVABILITY & HEALTH MONITOR');
        $this->info('================================================================');
        $this->line('Memulai pemindaian status kesehatan 7 pilar arsitektur platform...');

        $result = $healthService->check();

        $rows = [];
        foreach ($result['checks'] as $key => $check) {
            $statusBadge = $check['ok']
                ? '<fg=green;options=bold>✓ HEALTHY</>'
                : '<fg=red;options=bold>✗ UNHEALTHY</>';

            $rows[] = [
                $check['name'],
                $statusBadge,
                $check['message'],
            ];
        }

        $this->table(['Komponen / Sub-Sistem', 'Status', 'Keterangan Diagnostik'], $rows);

        $this->line('');
        $this->line("Waktu Selesai: {$result['timestamp']} (Durasi: {$result['duration_ms']} ms)");

        if ($result['audit_log_id']) {
            $this->line("Audit Log ID: #{$result['audit_log_id']}");
        }

        if ($result['status'] === 'HEALTHY') {
            $this->info('================================================================');
            $this->info('✓ SELURUH SUB-SISTEM BERFUNGSI SEMPURNA DENGAN INTEGRITAS PENUH');
            $this->info('================================================================');

            return self::SUCCESS;
        }

        $this->error('================================================================');
        $this->error('✗ DITEMUKAN GANGGUAN PADA SATU ATAU LEBIH SUB-SISTEM KRITIS');
        $this->error('================================================================');

        return self::FAILURE;
    }
}
