<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Treasury\Application\Actions\ReverseFxRevaluationAction;
use Modules\Treasury\Domain\Models\Revaluation;

class ReverseFxRevaluationCommand extends Command
{
    protected $signature = 'treasury:reverse-revaluations {--period= : Periode revaluasi yang akan dibalik}';

    protected $description = 'Pembalik otomatis revaluasi kurs awal periode baru';

    public function handle(ReverseFxRevaluationAction $action): int
    {
        $period = (string) $this->option('period');

        $query = Revaluation::where('is_reversed', false)->where('status', 'completed');
        if ($period !== '') {
            $query->where('period', $period);
        }

        $revaluations = $query->get();

        if ($revaluations->isEmpty()) {
            $this->info('Tidak ada revaluasi aktif yang perlu dibalik.');

            return self::SUCCESS;
        }

        $nextPeriod = date('Y-m');
        $reversedCount = 0;

        foreach ($revaluations as $reval) {
            $action->execute($reval, $nextPeriod);
            $reversedCount++;
            $this->line("Dibalik: {$reval->period} {$reval->currency} (ID: {$reval->id})");
        }

        $this->info("Total {$reversedCount} revaluasi berhasil dibalik.");

        return self::SUCCESS;
    }
}
