<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Reinsurance;

use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Apportionment\LargestRemainder;

/**
 * Alokasi program reasuransi (urutan baku): proporsional (quota share / surplus) lebih dulu,
 * lalu XoL per-risk / per-event atas retensi neto, lalu stop loss agregat atas loss ratio tahunan.
 *
 * Kompleksitas: O(L) per kerugian untuk L layer (layer sudah terurut menurut attachment);
 * pembagian premi surplus O(3 log 3) lewat LargestRemainder. Semua aritmetika integer/rasional eksak.
 */
final class ProgramAllocator
{
    /**
     * @param  list<XolLayer>  $layers  harus terurut menaik menurut attachment
     * @param  array<string, int>  $layerUsedIdr  pemakaian limit agregat per layer sebelum kerugian ini
     */
    public function allocateLoss(int $grossIdr, int $quotaShareBps, array $layers, array $layerUsedIdr = []): ProgramAllocationResult
    {
        if ($grossIdr < 0) {
            throw new InvalidArgumentException('Kerugian bruto tidak boleh negatif.');
        }
        if ($quotaShareBps < 0 || $quotaShareBps > 10000) {
            throw new InvalidArgumentException('Quota share harus 0–10000 bps.');
        }

        $previousAttachment = -1;
        foreach ($layers as $layer) {
            if ($layer->attachmentIdr <= $previousAttachment) {
                throw new InvalidArgumentException('Layer XoL harus terurut menaik menurut attachment.');
            }
            $previousAttachment = $layer->attachmentIdr;
        }

        $quotaShareCeded = $this->roundDiv($grossIdr * $quotaShareBps, 10000);
        $net = $grossIdr - $quotaShareCeded;

        $ceded = [];
        $reinstatement = [];
        $usedBefore = [];
        $remainingNet = $net;

        foreach ($layers as $layer) {
            $before = $layerUsedIdr[$layer->code] ?? 0;
            $usedBefore[$layer->code] = $before;

            $inLayer = min(max($net - $layer->attachmentIdr, 0), $layer->limitIdr);
            $available = max($layer->aggregateLimitIdr() - $before, 0);
            $take = min($inLayer, $available, $remainingNet);

            $ceded[$layer->code] = $take;
            $remainingNet -= $take;
            $reinstatement[$layer->code] = $take > 0 ? $this->reinstatementPremium($layer, $before, $before + $take) : 0;
        }

        return new ProgramAllocationResult(
            grossIdr: $grossIdr,
            quotaShareBps: $quotaShareBps,
            quotaShareCededIdr: $quotaShareCeded,
            netAfterQuotaShareIdr: $net,
            layerCededIdr: $ceded,
            reinstatementPremiumIdr: $reinstatement,
            layerUsedBeforeIdr: $usedBefore,
            retainedIdr: $remainingNet,
            layers: array_values($layers),
        );
    }

    /**
     * Premi reinstatement pro rata as to amount: reinstatement ke-k memulihkan pemakaian pada
     * rentang kumulatif ((k−1)·limit, k·limit].
     */
    public function reinstatementPremium(XolLayer $layer, int $usedBeforeIdr, int $usedAfterIdr): int
    {
        $sum = BigRational::zero();
        foreach ($layer->reinstatementRatesBps as $index => $rateBps) {
            $from = $index * $layer->limitIdr;
            $to = ($index + 1) * $layer->limitIdr;
            $overlap = min($usedAfterIdr, $to) - max($usedBeforeIdr, $from);
            if ($overlap <= 0 || $rateBps === 0) {
                continue;
            }

            $sum = $sum->plus(BigRational::of($overlap * $rateBps)->multipliedBy($layer->layerPremiumIdr)->dividedBy($layer->limitIdr * 10000));
        }

        return $sum->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    /**
     * Pembagian surplus atas uang pertanggungan: retensi 1 line, kapasitas surplus = $lines × retensi,
     * sisanya fakultatif.
     *
     * @return array{retained_idr: int, surplus_idr: int, facultative_idr: int}
     */
    public function splitSurplus(int $sumInsuredIdr, int $retentionLineIdr, int $lines): array
    {
        if ($sumInsuredIdr <= 0 || $retentionLineIdr <= 0 || $lines <= 0) {
            throw new InvalidArgumentException('Parameter surplus harus positif.');
        }

        $retained = min($sumInsuredIdr, $retentionLineIdr);
        $surplus = min($sumInsuredIdr - $retained, $lines * $retentionLineIdr);

        return [
            'retained_idr' => $retained,
            'surplus_idr' => $surplus,
            'facultative_idr' => $sumInsuredIdr - $retained - $surplus,
        ];
    }

    /**
     * Premi dibagi proporsional terhadap bagian uang pertanggungan (Hamilton; tie-break indeks menaik).
     *
     * @param  array{retained_idr: int, surplus_idr: int, facultative_idr: int}  $split
     * @return array{retained_idr: int, surplus_idr: int, facultative_idr: int}
     */
    public function apportionSurplusPremium(int $premiumIdr, array $split): array
    {
        $shares = (new LargestRemainder)->apportion($premiumIdr, [
            ['key' => 'retained_idr', 'weight' => $split['retained_idr']],
            ['key' => 'surplus_idr', 'weight' => $split['surplus_idr']],
            ['key' => 'facultative_idr', 'weight' => $split['facultative_idr']],
        ]);

        return [
            'retained_idr' => $shares['retained_idr'],
            'surplus_idr' => $shares['surplus_idr'],
            'facultative_idr' => $shares['facultative_idr'],
        ];
    }

    /**
     * Stop loss agregat: menanggung loss ratio di atas $attachmentLrBps sampai $limitLrBps (dari premi earned).
     */
    public function stopLoss(int $earnedPremiumIdr, int $incurredIdr, int $attachmentLrBps, int $limitLrBps): int
    {
        if ($earnedPremiumIdr <= 0 || $incurredIdr < 0 || $attachmentLrBps < 0 || $limitLrBps <= 0) {
            throw new InvalidArgumentException('Parameter stop loss tidak valid.');
        }

        $attachment = $this->roundDiv($earnedPremiumIdr * $attachmentLrBps, 10000);
        $limit = $this->roundDiv($earnedPremiumIdr * $limitLrBps, 10000);

        return min(max($incurredIdr - $attachment, 0), $limit);
    }

    private function roundDiv(int $numerator, int $denominator): int
    {
        return BigRational::of(BigInteger::of($numerator))->dividedBy($denominator)->toScale(0, RoundingMode::HalfUp)->toInt();
    }
}
