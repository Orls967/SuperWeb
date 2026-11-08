<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Illuminate\Console\Command;
use Modules\Aviation\Domain\Models\AviationAirport;
use Modules\Aviation\Domain\Models\AviationSlot;

class ReconcileSlotUsageCommand extends Command
{
    protected $signature = 'avi:slot-usage-evaluate {--airport= : Kode IATA bandara} {--season= : Kode season IATA (misal: W26)}';

    protected $description = 'Evaluasi rasio pemanfaatan slot bandara IATA (Aturan Use-it-or-lose-it 80/20) untuk penentuan hak historis musim berikutnya';

    public function handle(): int
    {
        $airportIata = $this->option('airport');
        $season = $this->option('season') ?? 'W26';

        $query = AviationSlot::with('airport')->where('season_code', $season);

        if ($airportIata) {
            $airport = AviationAirport::where('iata_code', $airportIata)->first();
            if (! $airport) {
                $this->error("Bandara dengan kode IATA {$airportIata} tidak ditemukan.");

                return self::FAILURE;
            }
            $query->where('airport_id', $airport->id);
        }

        $slots = $query->get();
        if ($slots->isEmpty()) {
            $this->info("Tidak ada slot ditemukan untuk season {$season}.");

            return self::SUCCESS;
        }

        $this->info("Memulai evaluasi pemanfaatan untuk {$slots->count()} slot season {$season}...");

        $retainedHistoric = 0;
        $lostHistoric = 0;

        foreach ($slots as $slot) {
            // Evaluasi aturan 80/20 (8000 bps)
            $isRetained = $slot->usage_rate_bps >= 8000;

            $slot->is_historic = $isRetained;
            if ($isRetained) {
                $retainedHistoric++;
            } else {
                $lostHistoric++;
                $slot->notes = ($slot->notes ? $slot->notes.'; ' : '').'Kehilangan hak historis karena utilisasi < 80%';
            }
            $slot->save();
        }

        $this->info("Rekonsiliasi selesai: {$retainedHistoric} slot mempertahankan hak historis, {$lostHistoric} slot kehilangan hak historis (kembali ke pool koordinator).");

        return self::SUCCESS;
    }
}

