<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Services\StockReservationService;

class ReservationSweepCommand extends Command
{
    protected $signature = 'wms:reservation-sweep';

    protected $description = 'Melepas reservasi stok gudang yang sudah melewati TTL';

    public function handle(StockReservationService $service): int
    {
        $expired = $service->expireStaleReservations();
        $this->info("Reservasi kedaluwarsa dilepas: {$expired}");

        return self::SUCCESS;
    }
}
