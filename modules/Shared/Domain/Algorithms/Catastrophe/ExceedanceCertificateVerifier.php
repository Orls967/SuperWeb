<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Catastrophe;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * Verifier kurva exceedance (PR2): hitung ulang agregat/maksimum dari YLT dan periksa peringkat & monotonisitas.
 */
final class ExceedanceCertificateVerifier
{
    public function verify(ExceedanceResult $result): true
    {
        $n = $result->years();
        if (count($result->aggregates) !== $n || count($result->maxima) !== $n) {
            throw new InvalidCertificateException('Panjang agregat/maksimum ≠ jumlah tahun');
        }

        foreach ($result->yearEvents as $i => $events) {
            if (array_sum($events) !== $result->aggregates[$i]) {
                throw new InvalidCertificateException("Agregat tahun {$i} tidak cocok dengan event");
            }
            if (($events === [] ? 0 : max($events)) !== $result->maxima[$i]) {
                throw new InvalidCertificateException("Maksimum tahun {$i} tidak cocok dengan event");
            }
        }

        $this->assertRanking($result->aggregates, $result->aggregateRanking, 'agregat');
        $this->assertRanking($result->maxima, $result->maximumRanking, 'maksimum');

        $expectedAal = intdiv(array_sum($result->aggregates) * 2 + $n, 2 * $n);
        if ($result->aalIdr !== $expectedAal) {
            throw new InvalidCertificateException('AAL tidak sama dengan Σ agregat / N (HalfUp)');
        }

        return true;
    }

    /**
     * @param  list<int>  $values
     * @param  list<int>  $ranking
     */
    private function assertRanking(array $values, array $ranking, string $label): void
    {
        if (count($ranking) !== count($values) || count(array_unique($ranking)) !== count($values)) {
            throw new InvalidCertificateException("Peringkat {$label} bukan permutasi");
        }

        for ($i = 1, $count = count($ranking); $i < $count; $i++) {
            $prev = $values[$ranking[$i - 1]];
            $cur = $values[$ranking[$i]];
            if ($cur > $prev || ($cur === $prev && $ranking[$i] < $ranking[$i - 1])) {
                throw new InvalidCertificateException("Peringkat {$label} tidak terurut turun / tie-break salah");
            }
        }
    }
}
