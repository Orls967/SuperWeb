<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Hotel\Domain\Adapters\ApoaSimulationAdapter;
use Modules\Hotel\Domain\Models\HotelApoaReport;
use Modules\Hotel\Domain\StateMachines\ApoaReportStateMachine;

final class HotelApoaSubmitCommand extends Command
{
    protected $signature = 'hotel:apoa-submit';

    protected $description = 'Mengirimkan antrean pelaporan tamu orang asing (APOA) ke gateway imigrasi';

    public function handle(): int
    {
        $pending = HotelApoaReport::where('status', ApoaReportStateMachine::STATUS_PENDING)->get();
        if ($pending->isEmpty()) {
            $this->info("Tidak ada laporan APOA tertunda.");
            return self::SUCCESS;
        }

        $payload = [];
        foreach ($pending as $p) {
            $payload[] = [
                'apoa_report_id' => (int) $p->id,
                'passport_blind_index' => $p->passport_blind_index,
                'nationality' => $p->nationality_country_code,
            ];
        }

        $batch = ApoaSimulationAdapter::submitBatch($payload);

        foreach ($pending as $p) {
            $p->update([
                'status' => ApoaReportStateMachine::STATUS_SUBMITTED,
                'apoa_submission_batch' => $batch['batch_id'],
                'submitted_at' => CarbonImmutable::now(),
            ]);
        }

        $this->info("Berhasil mengirim batch APOA {$batch['batch_id']} ({$pending->count()} tamu WNA).");
        return self::SUCCESS;
    }
}
