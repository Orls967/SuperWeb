<?php

declare(strict_types=1);

namespace Modules\Syariah\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\Core\Contracts\ClockInterface;
use Modules\Syariah\Application\Services\ZakatEngineService;
use Modules\Syariah\Domain\Models\SybZakatAssessment;

final class ZakatHaulAssessScheduledCommand extends Command
{
    protected $signature = 'syb:zakat-haul-assess {--haul-year= : Tahun haul hijriah}';

    protected $description = 'Proses terjadwal idempoten evaluasi haul dan kewajiban zakat mal muzakki';

    public function handle(ZakatEngineService $zakatService, ClockInterface $clock): int
    {
        $year = (int) ($this->option('haul-year') ?? 1447);
        $this->info("Menjalankan evaluasi haul zakat mal untuk tahun {$year}H...");

        $muzakkis = User::whereHas('rbacRoles', function ($q) {
            $q->where('name', 'customer');
        })->get();

        $admin = User::first() ?? User::factory()->create();
        $assessed = 0;

        foreach ($muzakkis as $muzakki) {
            $exists = SybZakatAssessment::query()
                ->where('customer_user_id', $muzakki->id)
                ->where('haul_year', $year)
                ->exists();

            if (! $exists) {
                $idempKey = "cron:zkt:haul:{$muzakki->id}:{$year}";
                $zakatService->assessHaul([
                    'customer_user_id' => $muzakki->id,
                    'haul_year' => $year,
                    'qualifying_wealth_idr' => 150_000_000,
                    'due_debts_idr' => 10_000_000,
                    'holding_days' => 354,
                    'auto_debit' => false,
                    'idempotency_key' => $idempKey,
                ], $admin);
                $assessed++;
            }
        }

        $this->info("Evaluasi haul zakat selesai: {$assessed} muzakki dinilai.");

        return self::SUCCESS;
    }
}
