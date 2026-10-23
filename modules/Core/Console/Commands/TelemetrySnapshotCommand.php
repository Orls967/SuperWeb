<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Telemetry\LiveTelemetryStreamService;

final class TelemetrySnapshotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telemetry:snapshot {--format=json : Output format: json or sse}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate real-time live telemetry snapshot across 6 enterprise command centers in JSON or SSE format';

    public function handle(LiveTelemetryStreamService $service): int
    {
        $format = strtolower((string) $this->option('format'));

        if ($format === 'sse') {
            $sseOutput = $service->toSseStream(eventsCount: 3);
            $this->output->write($sseOutput);

            return self::SUCCESS;
        }

        $snapshot = $service->getSnapshot();
        $this->line(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
