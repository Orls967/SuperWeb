<?php

declare(strict_types=1);

namespace Modules\Venue\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Contracts\ClockInterface;
use Modules\Venue\Domain\Models\VenuePermit;

class ScanPermitExpiryCommand extends Command
{
    protected $signature = 'venue:permit-expiry-scan';

    protected $description = 'Memindai izin keramaian yang mendekati masa kedaluwarsa atau telah kedaluwarsa (Fase 89)';

    public function handle(ClockInterface $clock): int
    {
        $now = $clock->now();
        $warningWindow = $now->copy()->addDays(7);

        $expiredPermits = VenuePermit::where('expires_at', '<', $now)
            ->where('status', '!=', 'EXPIRED')
            ->get();

        foreach ($expiredPermits as $permit) {
            $permit->status = 'EXPIRED';
            $permit->save();
            $this->warn("Izin {$permit->permit_number} untuk event {$permit->event?->title} telah KEDALUWARSA.");
        }

        $impendingPermits = VenuePermit::whereBetween('expires_at', [$now, $warningWindow])
            ->where('status', 'APPROVED')
            ->get();

        $this->info("Ditemukan {$expiredPermits->count()} izin kedaluwarsa dan {$impendingPermits->count()} izin mendekati batas.");

        return self::SUCCESS;
    }
}
