<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Aviation\Application\Actions\DispatchFlightGateAction;
use Modules\Aviation\Domain\Models\AviationFlight;

class DispatchScanCommand extends Command
{
    protected $signature = 'aviation:dispatch-scan {date : Tanggal evaluasi penerbangan YYYY-MM-DD}';

    protected $description = 'Memindai seluruh penerbangan terjadwal pada tanggal terkait dan mengevaluasi gerbang kelaikan A2';

    public function handle(DispatchFlightGateAction $dispatchAction): int
    {
        $dateStr = $this->argument('date');
        $evalDate = CarbonImmutable::parse($dateStr);

        $flights = AviationFlight::whereDate('departure_time', $evalDate->toDateString())
            ->whereIn('status', ['planned', 'slot_secured'])
            ->get();

        $this->info("Memindai {$flights->count()} penerbangan pada tanggal {$dateStr}");

        $cleared = 0;
        $blocked = 0;

        foreach ($flights as $f) {
            $result = $dispatchAction->evaluateEligibility($f, $evalDate);
            if ($result->isPermitted) {
                $cleared++;
            } else {
                $blocked++;
                $this->warn("Penerbangan {$f->flight_number} DIBLOKIR: ".implode(', ', $result->reasons));
            }
        }

        $this->info("Hasil pemindaian: {$cleared} siap lolos, {$blocked} diblokir kelaikan.");

        return self::SUCCESS;
    }
}

