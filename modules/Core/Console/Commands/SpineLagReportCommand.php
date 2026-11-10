<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Services\SpineLagMonitorService;

class SpineLagReportCommand extends Command
{
    protected $signature = 'spine:lag-report {--csv : Export output as CSV}';

    protected $description = 'Report current consumer lag metrics and overall nearest-rank p95 lag';

    public function handle(SpineLagMonitorService $service): int
    {
        $metrics = $service->getConsumerGroupMetrics();
        $p95 = $service->getOverallP95Lag();

        if ($this->option('csv')) {
            $this->line('consumer_group,topic,last_offset,max_offset,current_lag,updated_at');
            foreach ($metrics as $m) {
                $this->line("{$m['consumer_group']},{$m['topic']},{$m['last_offset']},{$m['max_offset']},{$m['current_lag']},{$m['updated_at']}");
            }

            return self::SUCCESS;
        }

        $this->info('=== EVENT SPINE CONSUMER LAG REPORT ===');
        if (empty($metrics)) {
            $this->info('No registered consumer groups found.');
        } else {
            $headers = ['Group', 'Topic', 'Last Offset', 'Max Offset', 'Lag', 'Last Updated'];
            $rows = array_map(function ($m) {
                return [
                    $m['consumer_group'],
                    $m['topic'],
                    $m['last_offset'],
                    $m['max_offset'],
                    $m['current_lag'],
                    $m['updated_at'] ?? '-',
                ];
            }, $metrics);

            $this->table($headers, $rows);
        }

        $this->info("Overall Nearest-Rank P95 Consumer Lag: {$p95}");

        return self::SUCCESS;
    }
}
