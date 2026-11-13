<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Actions\EvaluateRoboticsRoiProposalAction;
use Modules\Wms\Domain\Models\Warehouse;

/**
 * Command evaluasi proposal ROI otomasi J3 dalam aritmetika rasional eksak (D279-7, D279-9).
 */
final class RoboticsRoiCommand extends Command
{
    protected $signature = 'robotics:roi {proposal=PROP-279-CLI : Kode proposal} {--warehouse= : ID gudang} {--rate-num=1 : Pembilang r} {--rate-den=10 : Penyebut r}';

    protected $description = 'Evaluasi proposal ROI otomasi gudang (J3, asumsi_simulasi)';

    public function handle(EvaluateRoboticsRoiProposalAction $action): int
    {
        $code = (string) $this->argument('proposal');
        $whId = $this->option('warehouse');
        $rateNum = (int) $this->option('rate-num');
        $rateDen = (int) $this->option('rate-den');

        $warehouse = $whId !== null
            ? Warehouse::query()->find($whId)
            : Warehouse::query()->orderBy('code')->first();

        if ($warehouse === null) {
            $this->warn('Tidak ada gudang ditemukan untuk robotics:roi.');

            return self::SUCCESS;
        }

        $res = $action->evaluateAndStore(
            (string) $warehouse->id,
            $code,
            "Proposal Simulasi {$code}",
            1_000_000_000,
            [400_000_000, 400_000_000, 400_000_000, 400_000_000],
            $rateNum,
            $rateDen,
        );

        $prop = $res['proposal'];
        $this->info("robotics:roi {$prop->proposal_code}: NPV={$prop->npv_minor} payback={$prop->payback_months}m rec={$prop->recommendation} (asumsi_simulasi).");

        return self::SUCCESS;
    }
}
