<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Aviation\Domain\Models\AviationAircraft;

class MaintenanceForecastCommand extends Command
{
    protected $signature = 'aviation:maintenance-forecast';

    protected $description = 'Prakiraan jatuh tempo perawatan armada A-Check dan kedaluwarsa sertifikat kalender';

    public function handle(): int
    {
        $aircrafts = AviationAircraft::where('status', 'active')->get();
        $this->info("Menghitung prakiraan perawatan untuk {$aircrafts->count()} pesawat aktif");

        $warningCount = 0;
        $now = CarbonImmutable::now();

        foreach ($aircrafts as $ac) {
            $remainingHours = $ac->a_check_due_minutes - $ac->a_check_current_minutes;
            $expiryDays = $now->diffInDays(CarbonImmutable::parse($ac->airworthiness_expires_at), false);

            if ($remainingHours < 3000 || $expiryDays < 30) {
                $warningCount++;
                $this->warn("Perhatian {$ac->registration_mark}: Sisa A-Check {$remainingHours} mnt, Kedaluwarsa sertifikat {$expiryDays} hari.");
            }
        }

        $this->info("Prakiraan selesai: {$warningCount} unit mendekati batas pemeliharaan.");

        return self::SUCCESS;
    }
}

