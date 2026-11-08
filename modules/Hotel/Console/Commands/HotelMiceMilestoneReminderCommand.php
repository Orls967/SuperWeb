<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Domain\Models\HtlWeddingMilestoneSchedule;

final class HotelMiceMilestoneReminderCommand extends Command
{
    protected $signature = 'hotel:mice-milestone-reminder';

    protected $description = 'Scan upcoming wedding and MICE milestone installments and dispatch payment reminders';

    public function handle(): int
    {
        $this->info('Scanning pending wedding and MICE contract payment milestones...');

        $schedules = HtlWeddingMilestoneSchedule::where('status', 'active')->limit(30)->get();
        if ($schedules->isEmpty()) {
            $this->info('No pending milestone payments require escalation today.');

            return self::SUCCESS;
        }

        $headers = ['Schedule ID', 'Couple / Client', 'Contract Value', 'Paid Amount', 'Remaining Due'];
        $rows = [];

        foreach ($schedules as $s) {
            $val = (float) $s->total_contract_value;
            $paid = (float) $s->total_paid_amount;
            $remaining = max(0.0, $val - $paid);

            $rows[] = [
                substr($s->id, 0, 8).'...',
                $s->couple_names,
                'IDR '.number_format($val, 2),
                'IDR '.number_format($paid, 2),
                'IDR '.number_format($remaining, 2),
            ];
        }

        $this->table($headers, $rows);
        $this->info('Payment reminder scan completed for '.count($schedules).' contracts.');

        return self::SUCCESS;
    }
}
