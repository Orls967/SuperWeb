<?php

declare(strict_types=1);

namespace Modules\Core\Application\TickHandlers;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Contracts\SimTickHandlerInterface;
use Throwable;

class FranchiseRoyaltyTickHandler implements SimTickHandlerInterface
{
    public function name(): string
    {
        return 'royalti';
    }

    public function shouldRun(CarbonInterface $virtualNow, int $dayNumber): bool
    {
        return true; // daily
    }

    public function handle(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null): array
    {
        $exitCode = 0;
        $runner = $commandRunner ?? fn (string $cmd, array $args = []): int => Artisan::call($cmd, $args);

        try {
            $exitCode = $runner('resto:post-royalty', [
                '--date' => $virtualNow->toDateString(),
            ]);
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
