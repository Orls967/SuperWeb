<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Catastrophe;

use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Kurva exceedance dari Year Loss Table (YLT): AAL, AEP, OEP, PML dan TVaR pada periode ulang T.
 * PML_T = kerugian peringkat ⌈N/T⌉ (urut turun, 1-indexed, tie-break indeks tahun menaik);
 * TVaR_T = rata-rata (dibulatkan ke bawah) peringkat 1..⌈N/T⌉.
 *
 * Kompleksitas: O(N log N) untuk N tahun (dua sortir stabil); kueri PML/TVaR O(R).
 */
final class ExceedanceCurve
{
    /**
     * @param  list<list<int>>  $yearEvents  kerugian event per tahun, urut kejadian
     */
    public function build(array $yearEvents): ExceedanceResult
    {
        $n = count($yearEvents);
        if ($n === 0) {
            throw new InvalidArgumentException('YLT tidak boleh kosong.');
        }

        $aggregates = [];
        $maxima = [];
        foreach ($yearEvents as $events) {
            $sum = 0;
            $max = 0;
            foreach ($events as $loss) {
                if ($loss < 0) {
                    throw new InvalidArgumentException('Kerugian event tidak boleh negatif.');
                }
                $sum += $loss;
                $max = max($max, $loss);
            }
            $aggregates[] = $sum;
            $maxima[] = $max;
        }

        $aal = BigRational::of(array_sum($aggregates))->dividedBy($n)->toScale(0, RoundingMode::HalfUp)->toInt();

        return new ExceedanceResult(
            yearEvents: array_values($yearEvents),
            aggregates: $aggregates,
            maxima: $maxima,
            aggregateRanking: $this->ranking($aggregates),
            maximumRanking: $this->ranking($maxima),
            aalIdr: $aal,
        );
    }

    /** Kerugian agregat tahunan pada periode ulang T (AEP). */
    public function aepAt(ExceedanceResult $result, int $returnPeriod): int
    {
        return $result->aggregates[$result->aggregateRanking[$this->rank($result->years(), $returnPeriod) - 1]];
    }

    /** Kerugian event tunggal terbesar per tahun pada periode ulang T (OEP). */
    public function oepAt(ExceedanceResult $result, int $returnPeriod): int
    {
        return $result->maxima[$result->maximumRanking[$this->rank($result->years(), $returnPeriod) - 1]];
    }

    /** Tail value at risk atas agregat tahunan. */
    public function tvarAt(ExceedanceResult $result, int $returnPeriod): int
    {
        $rank = $this->rank($result->years(), $returnPeriod);
        $sum = BigInteger::zero();
        for ($i = 0; $i < $rank; $i++) {
            $sum = $sum->plus($result->aggregates[$result->aggregateRanking[$i]]);
        }

        return $sum->dividedBy($rank, RoundingMode::Down)->toInt();
    }

    /**
     * Cat XoL per event dengan limit agregat tahunan = limit × (1 + reinstatement).
     *
     * @param  list<list<int>>  $yearEvents
     * @return list<int> ceded per tahun
     */
    public function catXolCededPerYear(array $yearEvents, int $attachmentIdr, int $limitIdr, int $reinstatements): array
    {
        if ($attachmentIdr < 0 || $limitIdr <= 0 || $reinstatements < 0) {
            throw new InvalidArgumentException('Parameter Cat XoL tidak valid.');
        }

        $annualCap = $limitIdr * (1 + $reinstatements);
        $ceded = [];
        foreach ($yearEvents as $events) {
            $used = 0;
            foreach ($events as $loss) {
                $take = min(max($loss - $attachmentIdr, 0), $limitIdr, $annualCap - $used);
                $used += $take;
            }
            $ceded[] = $used;
        }

        return $ceded;
    }

    private function rank(int $years, int $returnPeriod): int
    {
        if ($returnPeriod < 1) {
            throw new InvalidArgumentException('Periode ulang minimal 1 tahun.');
        }

        return (int) ceil($years / $returnPeriod);
    }

    /**
     * @param  list<int>  $values
     * @return list<int> indeks urut turun menurut nilai, tie-break indeks menaik
     */
    private function ranking(array $values): array
    {
        $indices = array_keys($values);
        usort($indices, static fn (int $a, int $b): int => [$values[$b], $a] <=> [$values[$a], $b]);

        return $indices;
    }
}
