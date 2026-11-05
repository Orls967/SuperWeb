<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Modules\Insurance\Domain\DTOs\CessionLineData;
use Modules\Insurance\Domain\DTOs\CessionPlan;
use Modules\Insurance\Domain\Enums\CessionKind;
use Modules\Insurance\Domain\Enums\TreatyType;
use Modules\Insurance\Domain\Exceptions\CessionException;
use Modules\Insurance\Domain\Models\CessionEvent;
use Modules\Insurance\Domain\Models\InsuranceClaimPenuh;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;
use Modules\Insurance\Domain\Models\ReinsuranceTreaty;
use Modules\Shared\Domain\Algorithms\Apportionment\LargestRemainder;
use Modules\Shared\Domain\Algorithms\Reinsurance\ProgramAllocationCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Reinsurance\ProgramAllocator;
use Modules\Shared\Domain\Algorithms\Reinsurance\XolLayer;

/**
 * Mesin cession: mengubah premi/klaim bruto menjadi rencana cession per treaty, layer, dan reinsurer.
 * Urutan program baku: proporsional (QS → surplus → fakultatif) lalu XoL per-risk atas retensi neto.
 * Setiap alokasi XoL diverifikasi oleh ProgramAllocationCertificateVerifier; sertifikat tidak valid = exception (PR2).
 *
 * Kompleksitas: O(T·P) untuk T treaty dan P reinsurer pada panel; satu agregasi SQL per layer XoL.
 */
final class CessionEngine
{
    public function __construct(
        private readonly TreatyCatalog $catalog,
        private readonly ProgramAllocator $allocator,
        private readonly LargestRemainder $apportion,
        private readonly ProgramAllocationCertificateVerifier $verifier,
    ) {}

    public function planPremium(InsurancePolicyPenuh $policy): CessionPlan
    {
        $gross = (int) $policy->gross_premium_idr;
        $sumInsured = (int) $policy->sum_insured_idr;
        $treaties = $this->catalog->proportionalForPremium($policy->product->line_category, CarbonImmutable::parse($policy->starts_at));

        $lines = [];
        $snapshot = [
            'kind' => CessionKind::Premium->value,
            'policy_id' => $policy->id,
            'gross_idr' => $gross,
            'sum_insured_idr' => $sumInsured,
            'quota_share' => [],
            'surplus' => null,
            'uncovered_facultative_idr' => 0,
        ];

        $quotaShareBpsTotal = 0;
        $quotaShareCeded = 0;
        foreach ($treaties->where('type', TreatyType::QuotaShare) as $treaty) {
            $quotaShareBpsTotal += $treaty->quota_share_bps;
            if ($quotaShareBpsTotal > 10000) {
                throw new CessionException('Total quota share aktif melebihi 100% untuk lini '.$policy->product->line_category.'.');
            }

            $ceded = $this->roundDiv($gross * $treaty->quota_share_bps, 10000);
            $quotaShareCeded += $ceded;
            array_push($lines, ...$this->splitToPanel($treaty, 'QS', $ceded, $treaty->commission_bps));
            $snapshot['quota_share'][] = ['treaty_id' => $treaty->id, 'treaty_code' => $treaty->treaty_code, 'bps' => $treaty->quota_share_bps, 'commission_bps' => $treaty->commission_bps];
        }

        $surplusTreaty = $treaties->firstWhere('type', TreatyType::Surplus);
        if ($surplusTreaty !== null) {
            $baseSumInsured = $this->roundDiv($sumInsured * (10000 - $quotaShareBpsTotal), 10000);
            $premiumBase = $gross - $quotaShareCeded;
            $split = $this->allocator->splitSurplus(max($baseSumInsured, 1), $surplusTreaty->surplus_retention_idr, $surplusTreaty->surplus_lines);
            $premiumShares = $this->allocator->apportionSurplusPremium($premiumBase, $split);

            array_push($lines, ...$this->splitToPanel($surplusTreaty, 'SUR', $premiumShares['surplus_idr'], $surplusTreaty->commission_bps));

            $facultativeTreaty = $treaties->firstWhere('type', TreatyType::Facultative);
            if ($premiumShares['facultative_idr'] > 0) {
                if ($facultativeTreaty !== null) {
                    array_push($lines, ...$this->splitToPanel($facultativeTreaty, 'FAC', $premiumShares['facultative_idr'], $facultativeTreaty->commission_bps));
                } else {
                    $snapshot['uncovered_facultative_idr'] = $premiumShares['facultative_idr'];
                }
            }

            $snapshot['surplus'] = [
                'treaty_id' => $surplusTreaty->id,
                'treaty_code' => $surplusTreaty->treaty_code,
                'base_sum_insured_idr' => $baseSumInsured,
                'split' => $split,
                'premium_shares' => $premiumShares,
                'facultative_treaty_id' => $facultativeTreaty?->id,
                'commission_bps' => $surplusTreaty->commission_bps,
            ];
        }

        $ceded = array_sum(array_map(static fn (CessionLineData $l): int => $l->cededIdr, $lines));

        return new CessionPlan($gross, $gross - $ceded, $lines, $snapshot);
    }

    /**
     * Cession klaim atas kerugian bruto $grossLossIdr (setelah subrogasi). Proporsi mengikuti snapshot cession premi polis.
     */
    public function planClaim(InsuranceClaimPenuh $claim, int $grossLossIdr): CessionPlan
    {
        $policy = $claim->policy;
        $line = $policy->product->line_category;
        $premiumEvent = CessionEvent::query()->where('event_key', "ins:cede:premium:{$policy->id}")->first();
        $proportional = $premiumEvent?->snapshot ?? ['quota_share' => [], 'surplus' => null];

        $lines = [];
        $remaining = $grossLossIdr;
        $quotaShareCeded = 0;

        foreach ($proportional['quota_share'] as $qs) {
            $treaty = ReinsuranceTreaty::query()->with('panel')->findOrFail($qs['treaty_id']);
            $ceded = min($this->roundDiv($grossLossIdr * (int) $qs['bps'], 10000), $remaining);
            $remaining -= $ceded;
            $quotaShareCeded += $ceded;
            array_push($lines, ...$this->splitToPanel($treaty, 'QS', $ceded));
        }

        $surplus = $proportional['surplus'] ?? null;
        if ($surplus !== null && $remaining > 0) {
            $base = max((int) $surplus['base_sum_insured_idr'], 1);
            $netAfterQs = $remaining;

            $surplusTreaty = ReinsuranceTreaty::query()->with('panel')->findOrFail($surplus['treaty_id']);
            $surplusCeded = min($this->roundDiv($netAfterQs * (int) $surplus['split']['surplus_idr'], $base), $remaining);
            $remaining -= $surplusCeded;
            array_push($lines, ...$this->splitToPanel($surplusTreaty, 'SUR', $surplusCeded));

            if (($surplus['facultative_treaty_id'] ?? null) !== null) {
                $facTreaty = ReinsuranceTreaty::query()->with('panel')->findOrFail($surplus['facultative_treaty_id']);
                $facCeded = min($this->roundDiv($netAfterQs * (int) $surplus['split']['facultative_idr'], $base), $remaining);
                $remaining -= $facCeded;
                array_push($lines, ...$this->splitToPanel($facTreaty, 'FAC', $facCeded));
            }
        }

        $xolTreaties = $this->catalog->xolForLoss($line, CarbonImmutable::parse($claim->incident_date));
        $layers = $this->layersFor($xolTreaties);
        $used = $this->usedByTreaty($xolTreaties);
        $allocation = $this->allocator->allocateLoss($remaining, 0, array_values($layers), $used);
        $this->verifier->verify($allocation);

        foreach ($xolTreaties as $treaty) {
            $ceded = $allocation->layerCededIdr[$treaty->treaty_code] ?? 0;
            $reinstatement = $allocation->reinstatementPremiumIdr[$treaty->treaty_code] ?? 0;
            array_push($lines, ...$this->splitToPanel($treaty, 'XOL', $ceded, 0, $reinstatement));
        }

        $ceded = array_sum(array_map(static fn (CessionLineData $l): int => $l->cededIdr, $lines));
        $snapshot = [
            'kind' => CessionKind::Claim->value,
            'claim_id' => $claim->id,
            'gross_loss_idr' => $grossLossIdr,
            'premium_event_key' => $premiumEvent?->event_key,
            'xol_layers' => array_map(static fn (XolLayer $l): array => ['code' => $l->code, 'attachment_idr' => $l->attachmentIdr, 'limit_idr' => $l->limitIdr, 'reinstatement_rates_bps' => $l->reinstatementRatesBps, 'layer_premium_idr' => $l->layerPremiumIdr], array_values($layers)),
            'xol_used_before' => $used,
            'net_after_proportional_idr' => $remaining,
        ];

        $plan = new CessionPlan($grossLossIdr, $grossLossIdr - $ceded, $lines, $snapshot);
        if ($plan->retainedIdr < 0) {
            throw new CessionException('Cession klaim melebihi kerugian bruto.');
        }

        return $plan;
    }

    /**
     * Premi deposit treaty XoL/Cat/Stop loss yang dibukukan saat treaty aktif.
     */
    public function planTreatyPremium(ReinsuranceTreaty $treaty): CessionPlan
    {
        $premium = (int) $treaty->layer_premium_idr;
        $lines = $this->splitToPanel($treaty, 'DEP', $premium);

        return new CessionPlan($premium, 0, $lines, [
            'kind' => CessionKind::TreatyPremium->value,
            'treaty_id' => $treaty->id,
            'treaty_code' => $treaty->treaty_code,
            'layer_premium_idr' => $premium,
        ]);
    }

    /**
     * @param  EloquentCollection<int, ReinsuranceTreaty>  $treaties
     * @return array<string, XolLayer> kode treaty => layer
     */
    public function layersFor(EloquentCollection $treaties): array
    {
        $layers = [];
        foreach ($treaties as $treaty) {
            $layers[$treaty->treaty_code] = new XolLayer(
                code: $treaty->treaty_code,
                attachmentIdr: (int) $treaty->attachment_idr,
                limitIdr: (int) $treaty->limit_idr,
                reinstatementRatesBps: array_map('intval', $treaty->reinstatement_rates_bps ?? []),
                layerPremiumIdr: (int) $treaty->layer_premium_idr,
            );
        }

        return $layers;
    }

    /**
     * Pemakaian limit agregat per treaty (Σ ceded klaim sejauh ini), satu agregasi SQL.
     *
     * @param  EloquentCollection<int, ReinsuranceTreaty>  $treaties
     * @return array<string, int>
     */
    public function usedByTreaty(EloquentCollection $treaties): array
    {
        if ($treaties->isEmpty()) {
            return [];
        }

        $sums = DB::table('ins_cession_lines as l')
            ->join('ins_cession_events as e', 'e.id', '=', 'l.event_id')
            ->whereIn('l.treaty_id', $treaties->pluck('id')->all())
            ->where('e.kind', CessionKind::Claim->value)
            ->groupBy('l.treaty_id')
            ->orderBy('l.treaty_id')
            ->selectRaw('l.treaty_id, SUM(l.ceded_idr) as ceded')
            ->pluck('ceded', 'treaty_id');

        $used = [];
        foreach ($treaties as $treaty) {
            $used[$treaty->treaty_code] = (int) ($sums[$treaty->id] ?? 0);
        }

        return $used;
    }

    /**
     * Bagi $amount ke panel (Hamilton; tie-break urutan panel menaik menurut id).
     *
     * @return list<CessionLineData>
     */
    public function splitToPanel(ReinsuranceTreaty $treaty, string $layerCode, int $amount, int $commissionBps = 0, int $reinstatement = 0): array
    {
        if ($amount <= 0 && $reinstatement <= 0) {
            return [];
        }

        $panel = $treaty->panel;
        if ($panel->isEmpty()) {
            throw new CessionException("Treaty {$treaty->treaty_code} tidak memiliki panel reinsurer.");
        }

        $weights = $panel->map(static fn ($m): array => ['key' => (string) $m->reinsurer_party_id, 'weight' => (int) $m->share_bps])->values()->all();
        $cededShares = $this->apportion->apportion(max($amount, 0), $weights);
        $reinstatementShares = $this->apportion->apportion(max($reinstatement, 0), $weights);

        $lines = [];
        foreach ($panel as $member) {
            $partyId = (string) $member->reinsurer_party_id;
            $ceded = $cededShares[$partyId] ?? 0;
            $reinst = $reinstatementShares[$partyId] ?? 0;
            if ($ceded === 0 && $reinst === 0) {
                continue;
            }

            $lines[] = new CessionLineData(
                treatyId: $treaty->id,
                layerCode: $layerCode,
                reinsurerPartyId: $partyId,
                cededIdr: $ceded,
                commissionIdr: $commissionBps > 0 ? $this->roundDiv($ceded * $commissionBps, 10000) : 0,
                reinstatementIdr: $reinst,
            );
        }

        return $lines;
    }

    private function roundDiv(int $numerator, int $denominator): int
    {
        return BigRational::of($numerator)->dividedBy($denominator)->toScale(0, RoundingMode::HalfUp)->toInt();
    }
}
