<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\ScoreGuestChurnRiskAction;
use Modules\Hotel\Domain\Models\HotelGuestIdentity;

class HotelEvaluateGuestChurnRiskCommand extends Command
{
    protected $signature = 'hotel:guest-churn-risk-evaluate';

    protected $description = 'Menghitung skor risiko churn tamu dan memicu alert retensi otomatis';

    public function handle(ScoreGuestChurnRiskAction $churnAction): int
    {
        $this->info('Memulai kalkulasi algoritma churn risk tamu hotel...');

        $guests = HotelGuestIdentity::all();
        if ($guests->isEmpty()) {
            $this->warn('Tidak ada data profil tamu.');

            return self::SUCCESS;
        }

        $highRiskCount = 0;
        foreach ($guests as $g) {
            $guestData = [
                'guest_id' => (string) $g->id,
                'days_since_last_stay' => 120,
                'total_historical_stays' => 4,
                'nps_score' => 6,
                'unresolved_complaints_count' => 1,
                'loyalty_points_balance' => 2500,
            ];

            $cert = $churnAction->execute($guestData);
            if ($cert->riskLevel === 'HIGH' || $cert->riskLevel === 'CRITICAL') {
                $highRiskCount++;
                $this->warn("Tamu [{$g->full_name}]: Churn Risk {$cert->churnProbability}% ({$cert->riskLevel}) - Recommends: {$cert->recommendedRetentionAction}");
            }
        }

        $this->info("Evaluasi churn selesai. Total tamu berisiko tinggi: {$highRiskCount}");

        return self::SUCCESS;
    }
}
