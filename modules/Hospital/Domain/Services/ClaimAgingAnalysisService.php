<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Application\DTOs\ClaimAgingSummaryDTO;
use Modules\Hospital\Domain\Models\BpjsClaim;
use Modules\Hospital\Domain\Models\ClaimWaterfall;

/**
 * Layanan Domain Analisis Umur Piutang Klaim (Claim Aging & Impairment Analysis).
 * Mengelompokkan piutang klaim BPJS Kesehatan & asuransi swasta ke dalam bucket umur:
 *   - Lancar (0 - 30 hari)
 *   - Kurang Lancar (31 - 60 hari)
 *   - Diragukan (61 - 90 hari)
 *   - Macet / Lebih dari 90 hari (> 90 hari)
 * Menghitung estimasi cadangan kerugian piutang tak tertagih (Expected Credit Loss / Allowance).
 */
class ClaimAgingAnalysisService
{
    /**
     * Hitung ringkasan aging klaim asuransi & BPJS.
     */
    public function generateAgingSummary(?string $payerFilter = null): ClaimAgingSummaryDTO
    {
        $now = now();
        $query = ClaimWaterfall::whereIn('status', ['PENDING', 'ADJUDICATED', 'DISPUTED']);

        if ($payerFilter) {
            $query->where('payer_type', $payerFilter);
        }

        $waterfalls = $query->get();

        $c0to30 = 0;
        $c31to60 = 0;
        $c61to90 = 0;
        $cOver90 = 0;
        $unpaidClaims = [];

        foreach ($waterfalls as $wf) {
            $createdAt = $wf->created_at ?? $now;
            $ageDays = (int) $createdAt->diffInDays($now);
            $amount = $wf->covered_amount_idr > 0 ? $wf->covered_amount_idr : $wf->gross_billed_idr;

            if ($ageDays <= 30) {
                $c0to30 += $amount;
            } elseif ($ageDays <= 60) {
                $c31to60 += $amount;
            } elseif ($ageDays <= 90) {
                $c61to90 += $amount;
            } else {
                $cOver90 += $amount;
            }

            $unpaidClaims[] = [
                'id' => $wf->id,
                'episode_id' => $wf->billing_episode_id,
                'payer_type' => $wf->payer_type,
                'reference' => $wf->payer_reference,
                'amount_idr' => $amount,
                'age_days' => $ageDays,
                'status' => $wf->status,
            ];
        }

        $totalOutstanding = $c0to30 + $c31to60 + $c61to90 + $cOver90;

        // Formula provisi penurunan nilai (ECL):
        // 0-30: 1%, 31-60: 5%, 61-90: 20%, >90: 50%
        $impairmentAllowance = (int) round(
            ($c0to30 * 0.01) +
            ($c31to60 * 0.05) +
            ($c61to90 * 0.20) +
            ($cOver90 * 0.50)
        );

        return new ClaimAgingSummaryDTO(
            current0To30Idr: $c0to30,
            pastDue31To60Idr: $c31to60,
            pastDue61To90Idr: $c61to90,
            pastDueOver90Idr: $cOver90,
            totalOutstandingIdr: $totalOutstanding,
            estimatedImpairmentAllowanceIdr: $impairmentAllowance,
            unpaidClaims: $unpaidClaims,
        );
    }

    /**
     * Mengidentifikasi klaim yang berpotensi kadaluarsa atau membutuhkan intervensi banding segera.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getClaimsRequiringDisputeIntervention(): array
    {
        return ClaimWaterfall::where('status', 'DISPUTED')
            ->orWhere(function ($q) {
                $q->where('status', 'PENDING')
                    ->where('created_at', '<', now()->subDays(60));
            })
            ->get()
            ->map(fn ($wf) => [
                'waterfall_id' => $wf->id,
                'episode_id' => $wf->billing_episode_id,
                'payer_type' => $wf->payer_type,
                'reference' => $wf->payer_reference,
                'amount_idr' => $wf->covered_amount_idr,
                'days_pending' => (int) $wf->created_at->diffInDays(now()),
                'action_needed' => $wf->status === 'DISPUTED' ? 'RE_SUBMIT_CODING_CLARIFICATION' : 'EXPEDITE_COLLECTION',
            ])
            ->toArray();
    }
}
