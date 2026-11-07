<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\AuditDestinationPackageEscrowDiscrepancyAction;
use Modules\Hotel\Domain\Models\HtlDestinationPackageBooking;

class HotelReconcileEscrowPackagesCommand extends Command
{
    protected $signature = 'hotel:escrow-packages-reconcile';

    protected $description = 'Melakukan audit dan rekonsiliasi selisih dana escrow paket destinasi wisata multi-vendor';

    public function handle(AuditDestinationPackageEscrowDiscrepancyAction $auditAction): int
    {
        $this->info('Memulai audit rekening escrow paket perjalanan destinasi...');

        $bookings = HtlDestinationPackageBooking::where('escrow_status', '!=', 'FULLY_RELEASED')->get();
        if ($bookings->isEmpty()) {
            $this->info('Semua paket destinasi telah lunas dan escrow bersih.');

            return self::SUCCESS;
        }

        $discrepancyCount = 0;
        foreach ($bookings as $b) {
            $report = $auditAction->execute((string) $b->id);
            if (! $report['is_balanced']) {
                $this->warn("Ditemukan diskrepansi pada Booking [{$b->id}]: Discrepancy IDR {$report['discrepancy_idr']}");
                $discrepancyCount++;
            }
        }

        if ($discrepancyCount === 0) {
            $this->info('Audit selesai: 100% dana escrow seimbang dengan alokasi SSP multi-vendor.');
        } else {
            $this->error("Audit selesai dengan {$discrepancyCount} catatan diskrepansi escrow.");
        }

        return self::SUCCESS;
    }
}
