<?php

declare(strict_types=1);

namespace Modules\Venue\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Contracts\ClockInterface;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;

class ReleaseExpiredHoldsCommand extends Command
{
    protected $signature = 'venue:release-expired-holds';

    protected $description = 'Melepaskan reservasi meja atau tiket hold yang telah melewati batas kedaluwarsa pembayaran (Fase 89)';

    public function handle(ClockInterface $clock): int
    {
        $cutoff = $clock->now()->copy()->subMinutes(15);

        $expiredHolds = EntertainmentTableBooking::where('status', 'held')
            ->where('created_at', '<', $cutoff)
            ->get();

        $count = 0;
        foreach ($expiredHolds as $hold) {
            $hold->status = 'expired';
            $hold->save();
            $count++;
        }

        $this->info("Berhasil melepaskan {$count} hold meja kedaluwarsa.");

        return self::SUCCESS;
    }
}

