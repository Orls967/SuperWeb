<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Csp;

class ArcConsistency
{
    /**
     * D82-2 Implementasi AC-3 untuk mempersempit domain variabel konfigurator C2M.
     *
     * @param  array<string, list<mixed>>  $domains  Associative array [var => list of values]
     * @param  list<Constraint>  $constraints  Daftar constraint biner
     */
    public function enforce(array $domains, array $constraints): ArcConsistencyResult
    {
        // Salin domain
        $currentDomains = [];
        foreach ($domains as $var => $vals) {
            $currentDomains[$var] = array_values($vals);
        }

        // Bangun relasi tetangga dan antrean busur (arc queue)
        // Busur berarah: untuk setiap constraint biner (Xi, Xj), ada busur (Xi, Xj) dan (Xj, Xi)
        $queue = [];
        $neighbors = [];

        foreach ($constraints as $c) {
            $queue[] = [$c->var1, $c->var2, $c];
            // Balik constraint untuk busur sebaliknya
            $reversedConstraint = new Constraint(
                var1: $c->var2,
                var2: $c->var1,
                predicate: fn ($v2, $v1) => $c->check($v1, $v2),
                name: $c->name.'_rev'
            );
            $queue[] = [$c->var2, $c->var1, $reversedConstraint];

            $neighbors[$c->var1][] = [$c->var2, $reversedConstraint];
            $neighbors[$c->var2][] = [$c->var1, $c];
        }

        // Proses antrean AC-3
        while (! empty($queue)) {
            [$xi, $xj, $constraint] = array_shift($queue);

            if ($this->revise($currentDomains, $xi, $xj, $constraint)) {
                if (empty($currentDomains[$xi])) {
                    // Domain kosong -> inkonsisten
                    $hash = hash('sha256', json_encode($currentDomains, JSON_THROW_ON_ERROR).'0');

                    return new ArcConsistencyResult($currentDomains, false, $hash);
                }

                // Masukkan kembali busur tetangga (Xk, Xi) di mana Xk != Xj
                foreach ($neighbors[$xi] ?? [] as [$xk, $cRev]) {
                    if ($xk !== $xj) {
                        $queue[] = [$xk, $xi, $cRev];
                    }
                }
            }
        }

        $hash = hash('sha256', json_encode($currentDomains, JSON_THROW_ON_ERROR).'1');
        $result = new ArcConsistencyResult($currentDomains, true, $hash);

        // Verifikasi sertifikat internal
        ArcConsistencyCertificateVerifier::verify($domains, $constraints, $result);

        return $result;
    }

    /**
     * Hapus nilai v dari D(Xi) jika tidak ada w di D(Xj) yang memenuhi constraint.
     */
    private function revise(array &$domains, string $xi, string $xj, Constraint $constraint): bool
    {
        $revised = false;
        $domXi = $domains[$xi] ?? [];
        $domXj = $domains[$xj] ?? [];
        $newDomXi = [];

        foreach ($domXi as $xVal) {
            $hasSupport = false;
            foreach ($domXj as $yVal) {
                if ($constraint->check($xVal, $yVal)) {
                    $hasSupport = true;
                    break;
                }
            }

            if ($hasSupport) {
                $newDomXi[] = $xVal;
            } else {
                $revised = true;
            }
        }

        if ($revised) {
            $domains[$xi] = $newDomXi;
        }

        return $revised;
    }
}
