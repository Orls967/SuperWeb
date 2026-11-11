<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Domain\Models\ComplianceDeadline;

class CheckComplianceDeadlinesCommand extends Command
{
    protected $signature = 'ef:check-deadlines {--days=7 : Rentang hari ke depan untuk peringatan jatuh tempo}';

    protected $description = 'Memeriksa agenda kepatuhan regulasi yang mendekati atau melampaui batas waktu (54.6)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $targetDate = date('Y-m-d', strtotime("+{$days} days"));
        $today = date('Y-m-d');

        $this->info("Memeriksa tenggat waktu regulasi hingga {$targetDate} (rentang {$days} hari)...");

        $overdue = ComplianceDeadline::where('due_date', '<', $today)
            ->where('status', '!=', 'completed')
            ->get();

        $upcoming = ComplianceDeadline::whereBetween('due_date', [$today, $targetDate])
            ->where('status', '!=', 'completed')
            ->get();

        if ($overdue->isNotEmpty()) {
            $this->error("PERINGATAN: Terdapat {$overdue->count()} kewajiban regulasi yang telah OVERDUE!");
            foreach ($overdue as $od) {
                $this->line("  [OVERDUE] {$od->due_date} - {$od->regulatory_body}: {$od->title} (PIC: {$od->assigned_role})");
            }
        }

        if ($upcoming->isNotEmpty()) {
            $this->warn("Terdapat {$upcoming->count()} kewajiban regulasi mendekati jatuh tempo:");
            foreach ($upcoming as $up) {
                $this->line("  [UPCOMING] {$up->due_date} - {$up->regulatory_body}: {$up->title} (PIC: {$up->assigned_role})");
            }
        }

        if ($overdue->isEmpty() && $upcoming->isEmpty()) {
            $this->info('Seluruh agenda regulasi dalam kondisi aman dan terkendali.');
        }

        return $overdue->isNotEmpty() ? self::FAILURE : self::SUCCESS;
    }
}
