<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService;

class AuditSodConflictsCommand extends Command
{
    protected $signature = 'ef:audit-sod-conflicts';

    protected $description = 'Memindai seluruh otorisasi peran pengguna terhadap matriks Segregation of Duties (54.4)';

    public function handle(EnterpriseFinanceService $service): int
    {
        $this->info('Memulai audit matriks Segregation of Duties (SoD)...');

        $users = User::all();
        $totalConflicts = 0;

        foreach ($users as $user) {
            $roles = [$user->role ?? 'user'];
            $res = $service->checkSodConflict($roles);

            if ($res['has_conflict']) {
                $totalConflicts += $res['conflict_count'];
                $this->warn("User [{$user->id}] {$user->name} memiliki {$res['conflict_count']} konflik kewenangan!");
                foreach ($res['conflicts'] as $c) {
                    $this->line("  - [{$c['rule_code']}] {$c['description']} (Risk: {$c['risk']})");
                }
            }
        }

        if ($totalConflicts > 0) {
            $this->error("Audit SoD selesai dengan {$totalConflicts} potensi konflik kewenangan terdeteksi.");

            return self::FAILURE;
        }

        $this->info('Audit SoD selesai: 0 konflik kewenangan terdeteksi antar peran pengguna.');

        return self::SUCCESS;
    }
}
