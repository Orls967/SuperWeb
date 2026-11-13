<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Actions\ReserveZoneForMissionAction;

/**
 * Command terjadwal 5-menitan idempoten untuk melepas reservasi zona yang kedaluwarsa (D279-4, D279-9).
 */
final class RoboticsReservationSweepCommand extends Command
{
    protected $signature = 'robotics:reservation-sweep {--minute=60 : Menit batas akhir reservasi}';

    protected $description = 'Lepaskan reservasi zona lalu lintas yang telah melewati waktu selesai';

    public function handle(ReserveZoneForMissionAction $action): int
    {
        $minute = (int) $this->option('minute');
        $released = $action->sweepExpired($minute);

        $this->info("robotics:reservation-sweep selesai (released={$released}, minute={$minute}).");

        return self::SUCCESS;
    }
}
