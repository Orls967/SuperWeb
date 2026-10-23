<?php

declare(strict_types=1);

namespace Modules\Core\Application\TickHandlers;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Contracts\SimTickHandlerInterface;
use Throwable;

class DepreciationTickHandler implements SimTickHandlerInterface
{
    public function name(): string
    {
        return 'depresiasi';
    }

    public function shouldRun(CarbonInterface $virtualNow, int $dayNumber): bool
    {
        // Monthly on the 1st of the month, or day 1 of simulation
        return $virtualNow->day === 1 || $dayNumber === 1;
    }

    public function handle(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null): array
    {
        $period = $virtualNow->format('Y-m');
        $exitCode = 0;
        $runner = $commandRunner ?? fn (string $cmd, array $args = []): int => Artisan::call($cmd, $args);

        try {
            $exitCode = $runner('ast:depreciate', [
                '--period' => $period,
                '--book' => 'commercial',
            ]);
        } catch (Throwable) {
            $exitCode = -1;
        }

        return [
            'handler' => $this->name(),
            'period' => $period,
            'exit_code' => $exitCode,
        ];
    }
}
