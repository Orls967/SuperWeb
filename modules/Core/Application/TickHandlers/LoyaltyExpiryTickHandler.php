<?php

declare(strict_types=1);

namespace Modules\Core\Application\TickHandlers;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Contracts\SimTickHandlerInterface;
use Throwable;

class LoyaltyExpiryTickHandler implements SimTickHandlerInterface
{
    public function name(): string
    {
        return 'expiry_poin_voucher';
    }

    public function shouldRun(CarbonInterface $virtualNow, int $dayNumber): bool
    {
        return true; // daily
    }

    public function handle(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null): array
    {
        $pointsExit = 0;
        $vouchersExit = 0;
        $runner = $commandRunner ?? fn (string $cmd, array $args = []): int => Artisan::call($cmd, $args);

        try {
            $pointsExit = $runner('mall:expire-points', []);
        } catch (Throwable) {
            $pointsExit = -1;
        }

        try {
            $vouchersExit = $runner('mall:expire-vouchers', []);
        } catch (Throwable) {
            $vouchersExit = -1;
        }

        return [
            'handler' => $this->name(),
            'points_exit' => $pointsExit,
            'vouchers_exit' => $vouchersExit,
        ];
    }
}
