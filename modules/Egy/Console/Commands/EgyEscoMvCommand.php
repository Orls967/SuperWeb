<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Egy\Application\Actions\SettleEscoPerformanceContractAction;
use Modules\Egy\Domain\Models\EscoContract;

final class EgyEscoMvCommand extends Command
{
    protected $signature = 'egy:esco-mv {period? : Measurement and Verification period format YYYY-MM}';

    protected $description = 'Verifikasi pengukuran & penyelesaian performa energi ESCO IPMVP Option C';

    public function handle(SettleEscoPerformanceContractAction $action): int
    {
        $period = $this->argument('period') ?: Carbon::now()->format('Y-m');
        $this->info("Menjalankan M&V ESCO periode {$period}...");

        $contracts = EscoContract::whereIn('status', ['contracted', 'implementing', 'measuring', 'active'])->get();
        $settled = 0;

        foreach ($contracts as $contract) {
            $idempotencyKey = "EGY:ESCO:SETTLE:{$period}:{$contract->id}";
            $actualKwh = (int) intdiv($contract->baseline_monthly_kwh * 88, 100); // 88% konsumsi aktual (12% hemat)

            $settlement = $action->execute(
                contractId: $contract->id,
                periodMonth: $period,
                cddFactorBps: 11000, // 1.1 faktor cuaca
                actualKwh: $actualKwh,
                idempotencyKey: $idempotencyKey
            );

            $this->line(sprintf(
                'Kontrak [%s]: Gross Hemat %d kWh -> ESCO Share Rp %s | Client Share Rp %s',
                $contract->contract_number,
                $settlement->gross_savings_kwh,
                number_format($settlement->esco_share_idr),
                number_format($settlement->client_share_idr)
            ));

            $settled++;
        }

        $this->info("M&V ESCO selesai untuk {$settled} kontrak aktif.");

        return self::SUCCESS;
    }
}
