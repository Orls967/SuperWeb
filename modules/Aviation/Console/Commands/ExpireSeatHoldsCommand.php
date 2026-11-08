<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Models\AviationSeatHold;
use Modules\Aviation\Domain\Models\AviationSeatInventory;

class ExpireSeatHoldsCommand extends Command
{
    protected $signature = 'avi:expire-holds {--dry-run : Simulasi tanpa mengubah basis data}';

    protected $description = 'Batalkan reservasi kursi sementara (hold) yang melewati batas waktu TTL (15 menit) dan kembalikan kapasitas inventori';

    public function handle(): int
    {
        $this->info('Memeriksa reservasi kursi yang kedaluwarsa...');

        $now = now();
        $expiredHolds = AviationSeatHold::where('status', 'active')
            ->where('expires_at', '<', $now)
            ->get();

        if ($expiredHolds->isEmpty()) {
            $this->info('Tidak ada reservasi kursi kedaluwarsa yang perlu dilepaskan.');

            return self::SUCCESS;
        }

        $this->line("Ditemukan {$expiredHolds->count()} reservasi kursi kedaluwarsa.");

        if ($this->option('dry-run')) {
            $this->warn('[DRY-RUN] Melewati pelepasan kursi.');

            return self::SUCCESS;
        }

        $releasedCount = 0;
        foreach ($expiredHolds as $hold) {
            DB::transaction(function () use ($hold, &$releasedCount) {
                // Kunci inventori untuk mencegah race condition
                $inventory = AviationSeatInventory::where('flight_id', $hold->flight_id)
                    ->where('cabin', $hold->cabin)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    $inventory->held_seats = max(0, $inventory->held_seats - $hold->seat_count);
                    $inventory->save();
                }

                $hold->status = 'expired';
                $hold->save();
                $releasedCount++;
            });
        }

        $this->info("Berhasil melepaskan {$releasedCount} reservasi kursi yang kedaluwarsa kembali ke inventori penjualan.");

        return self::SUCCESS;
    }
}
