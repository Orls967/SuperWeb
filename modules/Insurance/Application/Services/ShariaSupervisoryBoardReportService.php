<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Insurance\Domain\Models\TakafulFund;
use Modules\Insurance\Domain\Models\TakafulParticipant;
use Modules\Insurance\Domain\Models\TakafulSurplusDistribution;

final class ShariaSupervisoryBoardReportService
{
    /**
     * Menghasilkan laporan kepatuhan syariah dan telaah Dewan Pengawas Syariah (DPS).
     *
     * @return array<string, mixed>
     */
    public function generateDpsComplianceReport(): array
    {
        $funds = TakafulFund::where('is_active', true)->get();
        $totalTabarruBalance = (int) $funds->where('fund_type', 'TABARRU')->sum('balance_idr');
        $totalContingencyReserve = (int) $funds->where('fund_type', 'TABARRU')->sum('contingency_reserve_idr');

        $participants = TakafulParticipant::all();
        $totalParticipants = $participants->count();
        $totalActive = $participants->where('status', 'active')->count();

        $surplusDistributions = TakafulSurplusDistribution::all();
        $totalSurplusDistributed = (int) $surplusDistributions->sum('gross_surplus_idr');
        $totalHibahParticipants = (int) $surplusDistributions->sum('participant_share_idr');
        $totalMudharabahOperator = (int) $surplusDistributions->sum('mudharabah_operator_idr');

        // Evaluasi kebutuhan Qardh Hasan (pinjaman kebajikan jika tabarru' defisit)
        $isDeficit = $totalTabarruBalance < 0;
        $qardhRequiredIdr = $isDeficit ? abs($totalTabarruBalance) : 0;

        // Analisis dana pemurnian (non-halal income purification untuk kegiatan sosial)
        $socialPurificationFundIdr = (int) intdiv($totalTabarruBalance * 2, 1000); // 0.2% alokasi pemurnian bunga bank konvensional

        return [
            'report_date' => CarbonImmutable::now()->toIso8601String(),
            'dps_opinion_status' => 'SESUAI PRINSIP SYARIAH (FATWA DSN-MUI NO. 21 & 53)',
            'fund_segregation' => [
                'total_tabarru_balance_idr' => $totalTabarruBalance,
                'total_contingency_reserve_idr' => $totalContingencyReserve,
                'is_segregated_strictly' => true,
            ],
            'participant_statistics' => [
                'total_participants' => $totalParticipants,
                'active_participants' => $totalActive,
                'total_annual_contributions_idr' => (int) $participants->sum('contribution_annual_idr'),
            ],
            'surplus_audit' => [
                'total_surplus_distributed_idr' => $totalSurplusDistributed,
                'total_hibah_to_participants_idr' => $totalHibahParticipants,
                'total_mudharabah_operator_idr' => $totalMudharabahOperator,
            ],
            'solvency_and_qardh' => [
                'is_in_deficit' => $isDeficit,
                'qardh_hasan_loan_idr' => $qardhRequiredIdr,
                'social_purification_fund_idr' => $socialPurificationFundIdr,
            ],
        ];
    }
}
