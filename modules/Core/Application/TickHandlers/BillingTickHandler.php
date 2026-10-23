<?php

declare(strict_types=1);

namespace Modules\Core\Application\TickHandlers;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Contracts\SimTickHandlerInterface;
use Throwable;

class BillingTickHandler implements SimTickHandlerInterface
{
    public function name(): string
    {
        return 'tagihan';
    }

    public function shouldRun(CarbonInterface $virtualNow, int $dayNumber): bool
    {
        // Monthly or day 1
        return $virtualNow->day === 1 || $dayNumber === 1;
    }

    public function handle(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null): array
    {
        $exitCode = 0;
        $runner = $commandRunner ?? fn (string $cmd, array $args = []): int => Artisan::call($cmd, $args);

        try {
            $exitCode = $runner('mall:generate-invoices', []);
        } catch (Throwable) {
            $exitCode = -1;
        }

        return [
            'handler' => $this->name(),
            'date' => $virtualNow->toDateString(),
            'exit_code' => $exitCode,
        ];
    }
}
