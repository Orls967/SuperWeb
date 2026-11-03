<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Csp;

class QuickXplain
{
    /**
     * D82-2 Menemukan himpunan konflik minimal (Minimal Conflict Set) dari pilihan pengguna
     * menggunakan algoritma QuickXplain (Junker, 2004) dengan urutan preferensi = urutan input.
     *
     * @param  list<Constraint>  $backgroundConstraints
     * @param  array<string, mixed>  $userAssignments  Associative array [var => val]
     */
    public function explain(array $backgroundConstraints, array $userAssignments): QuickXplainResult
    {
        // Ubah user assignments menjadi list berpasangan [key, value] mempertahankan urutan
        $elements = [];
        foreach ($userAssignments as $var => $val) {
            $elements[] = [$var, $val];
        }

        // Jalankan rekursi QuickXplain
        $conflictElements = $this->qx($backgroundConstraints, [], $elements);

        $conflict = [];
        foreach ($conflictElements as [$var, $val]) {
            $conflict[$var] = $val;
        }

        $certificate = hash('sha256', json_encode($conflict, JSON_THROW_ON_ERROR));
        $result = new QuickXplainResult($conflict, $certificate);

        // Verifikasi sertifikat internal
        QuickXplainCertificateVerifier::verify($backgroundConstraints, $userAssignments, $result);

        return $result;
    }

    /**
     * Rekursi QuickXplain:
     * B: Background knowledge
     * Delta: partial conflict found so far
     * C: remaining candidates
     *
     * @param  list<Constraint>  $background
     * @param  list<array{0: string, 1: mixed}>  $delta
     * @param  list<array{0: string, 1: mixed}>  $candidates
     * @return list<array{0: string, 1: mixed}>
     */
    private function qx(array $background, array $delta, array $candidates): array
    {
        if (! empty($delta) && $this->isInconsistent($background, $delta)) {
            return [];
        }

        if (count($candidates) === 1) {
            return $candidates;
        }

        $k = (int) floor(count($candidates) / 2);
        $c1 = array_slice($candidates, 0, $k);
        $c2 = array_slice($candidates, $k);

        $d1 = $this->qx($background, array_merge($delta, $c1), $c2);
        $d2 = $this->qx($background, array_merge($delta, $d1), $c1);

        return array_merge($d1, $d2);
    }

    /**
     * Cek apakah himpunan penugasan melanggar setidaknya satu constraint latar belakang.
     *
     * @param  list<Constraint>  $background
     * @param  list<array{0: string, 1: mixed}>  $assignments
     */
    private function isInconsistent(array $background, array $assignments): bool
    {
        $map = [];
        foreach ($assignments as [$var, $val]) {
            $map[$var] = $val;
        }

        foreach ($background as $c) {
            if (array_key_exists($c->var1, $map) && array_key_exists($c->var2, $map)) {
                if (! $c->check($map[$c->var1], $map[$c->var2])) {
                    return true;
                }
            }
        }

        return false;
    }
}
