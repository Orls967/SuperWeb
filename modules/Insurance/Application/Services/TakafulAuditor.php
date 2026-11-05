<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Modules\Insurance\Domain\Models\AgriInsuranceClaim;
use Modules\Insurance\Domain\Models\InsuranceGrievanceTicket;
use Modules\Insurance\Domain\Models\TakafulFund;
use Modules\Insurance\Domain\Models\TakafulParticipant;
use Modules\Insurance\Domain\Models\TakafulSurplusDistribution;
use RuntimeException;

final class TakafulAuditor
{
    /**
     * Menjalankan audit dua-sumber independen untuk Takaful Window, Agri Insurance & Compliance (Fase 160).
     *
     * @return array{
     *     status: string,
     *     total_funds: int,
     *     total_participants: int,
     *     total_surplus_records: int,
     *     total_agri_claims: int,
     *     total_grievances: int,
     *     fund_deficit_violations: int,
     *     surplus_allocation_mismatches: int,
     *     contribution_ratio_mismatches: int,
     *     agri_claim_offset_mismatches: int,
     *     grievance_sla_violations: int
     * }
     */
    public function audit(): array
    {
        $funds = TakafulFund::all();
        $deficitViolations = 0;
        foreach ($funds as $f) {
            if ((int) $f->balance_idr < 0) {
                $deficitViolations++;
            }
        }

        $participants = TakafulParticipant::all();
        $ratioMismatches = 0;
        foreach ($participants as $p) {
            if (((int) $p->tabarru_portion_bps + (int) $p->ujrah_portion_bps) !== 10000) {
                $ratioMismatches++;
            }
        }

        $surplusRecords = TakafulSurplusDistribution::all();
        $surplusMismatches = 0;
        foreach ($surplusRecords as $s) {
            $sumShares = (int) ($s->participant_share_idr + $s->contingency_reserve_idr + $s->mudharabah_operator_idr);
            if ($sumShares !== (int) $s->gross_surplus_idr) {
                $surplusMismatches++;
            }
        }

        $agriClaims = AgriInsuranceClaim::all();
        $agriClaimMismatches = 0;
        foreach ($agriClaims as $claim) {
            $sumParts = (int) ($claim->loan_offset_amount_idr + $claim->net_farmer_payout_idr);
            if ($sumParts !== (int) $claim->gross_claim_idr) {
                $agriClaimMismatches++;
            }
        }

        $grievances = InsuranceGrievanceTicket::all();
        $slaViolations = 0;
        foreach ($grievances as $g) {
            if ($g->status === 'resolved' && ($g->resolved_at === null || empty($g->resolution_summary))) {
                $slaViolations++;
            }
        }

        if (
            $deficitViolations > 0
            || $ratioMismatches > 0
            || $surplusMismatches > 0
            || $agriClaimMismatches > 0
            || $slaViolations > 0
        ) {
            throw new RuntimeException(sprintf(
                'Audit dua-sumber Takaful & Agri GAGAL! Mismatch: Deficit=%d, Ratio=%d, Surplus=%d, AgriClaims=%d, SLA=%d',
                $deficitViolations,
                $ratioMismatches,
                $surplusMismatches,
                $agriClaimMismatches,
                $slaViolations
            ));
        }

        return [
            'status' => 'CLEAN',
            'total_funds' => $funds->count(),
            'total_participants' => $participants->count(),
            'total_surplus_records' => $surplusRecords->count(),
            'total_agri_claims' => $agriClaims->count(),
            'total_grievances' => $grievances->count(),
            'fund_deficit_violations' => 0,
            'surplus_allocation_mismatches' => 0,
            'contribution_ratio_mismatches' => 0,
            'agri_claim_offset_mismatches' => 0,
            'grievance_sla_violations' => 0,
        ];
    }
}
