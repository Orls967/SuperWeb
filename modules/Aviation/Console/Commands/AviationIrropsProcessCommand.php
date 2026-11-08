<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Illuminate\Console\Command;
use Modules\Aviation\Application\Services\AviationIrropsService;
use Modules\Aviation\Domain\Models\AviationFlight;

class AviationIrropsProcessCommand extends Command
{
    protected $signature = 'avi:irrops-process
                            {flight_id : ID penerbangan yang terdampak disrupsi}
                            {--category=AOG_TECHNICAL : Kategori disrupsi (AOG_TECHNICAL, FORCE_MAJEURE_WEATHER, FORCE_MAJEURE_VOLCANIC_ERUPTION, CANCELLATION)}
                            {--delay=180 : Durasi keterlambatan dalam menit}
                            {--desc=Keterlambatan operasional : Penjelasan insiden}
                            {--alt-flight= : ID penerbangan alternatif pengganti}';

    protected $description = 'Proses otomasi penanganan disrupsi IRROPS dan penerbitan hak penumpang PM 89';

    public function handle(AviationIrropsService $irropsService): int
    {
        $flightId = (int) $this->argument('flight_id');
        $category = (string) $this->option('category');
        $delay = (int) $this->option('delay');
        $desc = (string) $this->option('desc');
        $altFlight = $this->option('alt-flight') !== null ? (int) $this->option('alt-flight') : null;

        $flight = AviationFlight::find($flightId);
        if ($flight === null) {
            $this->error("Penerbangan dengan ID [{$flightId}] tidak ditemukan.");

            return 1;
        }

        $this->info("Memulai orkestrasi IRROPS untuk penerbangan [{$flight->flight_number}]...");

        $res = $irropsService->handleFlightDisruption([
            'flight_id' => $flightId,
            'incident_category' => $category,
            'delay_minutes' => $delay,
            'incident_description' => $desc,
            'alternative_flight_id' => $altFlight,
        ]);

        $this->info("Kasus disrupsi [ID: {$res['disruption_case_id']}] berhasil diproses (Saga: {$res['saga_id']}).");
        $this->line("- Status: {$res['status']}");
        $this->line("- E-Voucher Diterbitkan: {$res['vouchers_dispatched']}");
        $this->line("- Kamar Hotel Dialokasikan: {$res['hotel_rooms']}");

        return 0;
    }
}
