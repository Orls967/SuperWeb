<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Lp;

use Brick\Math\BigRational;
use InvalidArgumentException;

/**
 * Pemecah Linear Programming Eksak menggunakan Simplex Dua Fase (Two-Phase Simplex)
 * dengan Aturan Bland untuk pencegahan perulangan (cycling) (PR2, T-4).
 *
 * Semua perhitungan memakai BigRational eksak tanpa float.
 * Menghasilkan solusi prima x* dan sertifikat dual y* (atau sertifikat Farkas bila tidak layak).
 */
final class RationalSimplex
{
    public const int MAX_VARS = 30;

    public const int MAX_CONSTRAINTS = 20;

    /**
     * Memaksimalkan c^T * x dengan kendala A * x <= b, x >= 0.
     *
     * @param  list<BigRational|int|string>  $c  Koefisien fungsi tujuan (panjang n)
     * @param  list<list<BigRational|int|string>>  $A  Matriks kendala m x n
     * @param  list<BigRational|int|string>  $b  Sisi kanan kendala (panjang m)
     *
     * @throws InvalidArgumentException Bila ukuran melebihi batas atau dimensi tidak konsisten
     */
    public function solve(
        array $c,
        array $A,
        array $b,
        ?LpCertificateVerifier $verifier = null
    ): LpResult {
        $m = count($A);
        $n = count($c);

        if ($n > self::MAX_VARS) {
            throw new InvalidArgumentException("Jumlah variabel ({$n}) melebihi batas maksimum 30");
        }
        if ($m > self::MAX_CONSTRAINTS) {
            throw new InvalidArgumentException("Jumlah batasan ({$m}) melebihi batas maksimum 20");
        }
        if (count($b) !== $m) {
            throw new InvalidArgumentException('Jumlah elemen b ('.count($b).") tidak cocok dengan baris A ({$m})");
        }

        // Normalisasi ke BigRational
        /** @var list<BigRational> $cR */
        $cR = array_map(fn ($val): BigRational => $val instanceof BigRational ? $val : BigRational::of($val), $c);
        /** @var list<list<BigRational>> $AR */
        $AR = [];
        foreach ($A as $row) {
            if (count($row) !== $n) {
                throw new InvalidArgumentException('Setiap baris matriks A harus memiliki panjang n = '.$n);
            }
            $AR[] = array_map(fn ($val): BigRational => $val instanceof BigRational ? $val : BigRational::of($val), $row);
        }
        /** @var list<BigRational> $bR */
        $bR = array_map(fn ($val): BigRational => $val instanceof BigRational ? $val : BigRational::of($val), $b);

        if ($m === 0 || $n === 0) {
            $res = new LpResult(
                LpStatus::OPTIMAL,
                array_fill(0, $n, BigRational::zero()),
                BigRational::zero(),
                array_fill(0, $m, BigRational::zero())
            );
            $verifier ??= new LpCertificateVerifier;
            $verifier->verify($cR, $AR, $bR, $res);

            return $res;
        }

        $result = $this->solveInternal($cR, $AR, $bR);

        $verifier ??= new LpCertificateVerifier;
        $verifier->verify($cR, $AR, $bR, $result);

        return $result;
    }

    /**
     * @param  list<BigRational>  $c
     * @param  list<list<BigRational>>  $A
     * @param  list<BigRational>  $b
     */
    private function solveInternal(array $c, array $A, array $b): LpResult
    {
        $m = count($A);
        $n = count($c);

        // Cek apakah ada b_i < 0
        $needsPhase1 = false;
        foreach ($b as $bi) {
            if ($bi->isNegative()) {
                $needsPhase1 = true;
                break;
            }
        }

        if (! $needsPhase1) {
            // Semua b_i >= 0, slack variables langsung menjadi basis awal yang layak
            return $this->solvePhase2Direct($c, $A, $b);
        }

        return $this->solveTwoPhase($c, $A, $b);
    }

    /**
     * Menyelesaikan langsung fase 2 bila b >= 0.
     *
     * @param  list<BigRational>  $c
     * @param  list<list<BigRational>>  $A
     * @param  list<BigRational>  $b
     */
    private function solvePhase2Direct(array $c, array $A, array $b): LpResult
    {
        $m = count($A);
        $n = count($c);

        // Tableau: baris 0..m-1 untuk kendala, baris m untuk fungsi tujuan
        // Kolom: 0..n-1 (x), n..n+m-1 (s), n+m (RHS)
        $totalCols = $n + $m + 1;
        $rhsCol = $totalCols - 1;

        $table = [];
        $basis = [];

        for ($i = 0; $i < $m; $i++) {
            $row = array_fill(0, $totalCols, BigRational::zero());
            for ($j = 0; $j < $n; $j++) {
                $row[$j] = $A[$i][$j];
            }
            $row[$n + $i] = BigRational::one(); // Slack variable
            $row[$rhsCol] = $b[$i];
            $table[] = $row;
            $basis[$i] = $n + $i;
        }

        // Baris fungsi tujuan: max z = c*x => z - c*x = 0
        // Reduksi awal: koefisien x_j adalah -c_j
        $objRow = array_fill(0, $totalCols, BigRational::zero());
        for ($j = 0; $j < $n; $j++) {
            $objRow[$j] = $c[$j]->negated();
        }
        $table[] = $objRow;

        return $this->runSimplexTableau($table, $basis, $m, $n, $c, $A, $b);
    }

    /**
     * Menyelesaikan dua fase bila terdapat kendala dengan b_i < 0.
     *
     * @param  list<BigRational>  $c
     * @param  list<list<BigRational>>  $A
     * @param  list<BigRational>  $b
     */
    private function solveTwoPhase(array $c, array $A, array $b): LpResult
    {
        $m = count($A);
        $n = count($c);

        // Siapkan baris kendala terstandarisasi dengan RHS >= 0
        // Untuk baris dengan b_i < 0: kalikan dengan -1, slack bertanda -1, tambahkan artificial a_i bertanda +1
        $artificialVars = [];
        $numArt = 0;

        for ($i = 0; $i < $m; $i++) {
            if ($b[$i]->isNegative()) {
                $artificialVars[$i] = $n + $m + $numArt;
                $numArt++;
            }
        }

        $totalCols = $n + $m + $numArt + 1;
        $rhsCol = $totalCols - 1;

        $table = [];
        $basis = [];

        for ($i = 0; $i < $m; $i++) {
            $row = array_fill(0, $totalCols, BigRational::zero());
            if ($b[$i]->isNegative()) {
                // Kalikan dengan -1
                for ($j = 0; $j < $n; $j++) {
                    $row[$j] = $A[$i][$j]->negated();
                }
                $row[$n + $i] = BigRational::one()->negated(); // -s_i
                $artCol = $artificialVars[$i];
                $row[$artCol] = BigRational::one(); // +a_i
                $row[$rhsCol] = $b[$i]->negated();
                $basis[$i] = $artCol;
            } else {
                for ($j = 0; $j < $n; $j++) {
                    $row[$j] = $A[$i][$j];
                }
                $row[$n + $i] = BigRational::one(); // +s_i
                $row[$rhsCol] = $b[$i];
                $basis[$i] = $n + $i;
            }
            $table[] = $row;
        }

        // Fase 1: Min Σ a_i <=> Max -Σ a_i <=> w + Σ a_i = 0
        // Nyatakan dalam bentuk kanonik terhadap variabel basis
        $wRow = array_fill(0, $totalCols, BigRational::zero());
        foreach ($artificialVars as $artCol) {
            $wRow[$artCol] = BigRational::one();
        }
        foreach ($artificialVars as $i => $artCol) {
            // wRow -= row[i]
            for ($k = 0; $k < $totalCols; $k++) {
                $wRow[$k] = $wRow[$k]->minus($table[$i][$k]);
            }
        }
        $table[] = $wRow;

        // Jalankan Fase 1: hanya kolom non-artifisial (0 .. n + m - 1) yang boleh masuk
        $this->pivotUntilOptimal($table, $basis, $m, $n + $m);

        $phase1Obj = $table[$m][$rhsCol];

        if ($phase1Obj->isNegative()) {
            // INFEASIBLE: Ambil sertifikat Farkas dari koefisien slack pada baris fungsi tujuan Fase 1
            // Baris w menyatakan relasi dual Farkas
            $farkasY = [];
            for ($i = 0; $i < $m; $i++) {
                $slackCol = $n + $i;
                $yi = $table[$m][$slackCol];
                // Amankan dari nilai negatif kecil (nol)
                if ($yi->isNegative() && $yi->abs()->isLessThan(BigRational::of('1/1000000000'))) {
                    $yi = BigRational::zero();
                }
                $farkasY[] = $yi;
            }

            return new LpResult(LpStatus::INFEASIBLE, [], null, $farkasY);
        }

        // Fase 1 sukses (obj == 0). Hapus kolom artifisial dan baris w
        // Keluarkan variabel artifisial dari basis bila bernilai nol
        for ($i = 0; $i < $m; $i++) {
            if ($basis[$i] >= $n + $m) {
                // Cari kolom non-artifisial untuk pivot
                for ($j = 0; $j < $n + $m; $j++) {
                    if (! $table[$i][$j]->isZero()) {
                        $this->pivot($table, $basis, $i, $j);
                        break;
                    }
                }
            }
        }

        // Buat tableau Fase 2: kolom 0..n+m-1 dan RHS
        $phase2Cols = $n + $m + 1;
        $phase2Table = [];
        for ($i = 0; $i < $m; $i++) {
            $row = array_fill(0, $phase2Cols, BigRational::zero());
            for ($j = 0; $j < $n + $m; $j++) {
                $row[$j] = $table[$i][$j];
            }
            $row[$phase2Cols - 1] = $table[$i][$rhsCol];
            $phase2Table[] = $row;
        }

        // Baris fungsi tujuan Fase 2: c * x
        $objRow = array_fill(0, $phase2Cols, BigRational::zero());
        for ($j = 0; $j < $n; $j++) {
            $objRow[$j] = $c[$j]->negated();
        }

        // Kanonikalkan baris fungsi tujuan terhadap basis saat ini
        for ($i = 0; $i < $m; $i++) {
            $bCol = $basis[$i];
            if ($bCol < $n) {
                $cBasis = $c[$bCol];
                for ($k = 0; $k < $phase2Cols; $k++) {
                    $objRow[$k] = $objRow[$k]->plus($cBasis->multipliedBy($phase2Table[$i][$k]));
                }
            }
        }
        $phase2Table[] = $objRow;

        return $this->runSimplexTableau($phase2Table, $basis, $m, $n, $c, $A, $b);
    }

    /**
     * @param  list<list<BigRational>>  $table
     * @param  array<int, int>  $basis
     * @param  list<BigRational>  $c
     * @param  list<list<BigRational>>  $A
     * @param  list<BigRational>  $b
     */
    private function runSimplexTableau(
        array &$table,
        array &$basis,
        int $m,
        int $n,
        array $c,
        array $A,
        array $b
    ): LpResult {
        $totalVars = $n + $m;
        $rhsCol = $totalVars;

        while (true) {
            // Aturan Bland untuk kolom masuk (entering): indeks terkecil dengan koefisien negatif pada baris z
            // (Karena baris z adalah z - c*x = 0, koefisien negatif berarti reduced cost positif yang meningkatkan z)
            $pivotCol = -1;
            for ($j = 0; $j < $totalVars; $j++) {
                if ($table[$m][$j]->isNegative()) {
                    $pivotCol = $j;
                    break;
                }
            }

            if ($pivotCol === -1) {
                // Optimal tercapai
                break;
            }

            // Aturan Bland untuk baris keluar (leaving): minimum ratio test
            $pivotRow = -1;
            $minRatio = null;

            for ($i = 0; $i < $m; $i++) {
                $coeff = $table[$i][$pivotCol];
                if ($coeff->isPositive()) {
                    $ratio = $table[$i][$rhsCol]->dividedBy($coeff);
                    if ($minRatio === null || $ratio->isLessThan($minRatio)) {
                        $minRatio = $ratio;
                        $pivotRow = $i;
                    } elseif ($ratio->isEqualTo($minRatio)) {
                        // Tie break: pilih variabel basis dengan indeks terkecil
                        if ($basis[$i] < $basis[$pivotRow]) {
                            $pivotRow = $i;
                        }
                    }
                }
            }

            if ($pivotRow === -1) {
                // Unbounded
                return new LpResult(LpStatus::UNBOUNDED, [], null, []);
            }

            $this->pivot($table, $basis, $pivotRow, $pivotCol);
        }

        // Ekstraksi solusi x*
        $x = array_fill(0, $n, BigRational::zero());
        for ($i = 0; $i < $m; $i++) {
            if ($basis[$i] < $n) {
                $x[$basis[$i]] = $table[$i][$rhsCol];
            }
        }

        $objVal = $table[$m][$rhsCol];

        // Ekstraksi solusi dual y* dari koefisien slack pada baris fungsi tujuan
        $y = [];
        for ($i = 0; $i < $m; $i++) {
            $slackCol = $n + $i;
            $yi = $table[$m][$slackCol];
            if ($yi->isNegative() && $yi->abs()->isLessThan(BigRational::of('1/1000000000'))) {
                $yi = BigRational::zero();
            }
            $y[] = $yi;
        }

        return new LpResult(LpStatus::OPTIMAL, $x, $objVal, $y);
    }

    /**
     * @param  list<list<BigRational>>  $table
     * @param  array<int, int>  $basis
     */
    private function pivotUntilOptimal(array &$table, array &$basis, int $m, int $totalVars): void
    {
        $rhsCol = count($table[0]) - 1;

        while (true) {
            $pivotCol = -1;
            for ($j = 0; $j < $totalVars; $j++) {
                if ($table[$m][$j]->isNegative()) {
                    $pivotCol = $j;
                    break;
                }
            }

            if ($pivotCol === -1) {
                break;
            }

            $pivotRow = -1;
            $minRatio = null;

            for ($i = 0; $i < $m; $i++) {
                $coeff = $table[$i][$pivotCol];
                if ($coeff->isPositive()) {
                    $ratio = $table[$i][$rhsCol]->dividedBy($coeff);
                    if ($minRatio === null || $ratio->isLessThan($minRatio)) {
                        $minRatio = $ratio;
                        $pivotRow = $i;
                    } elseif ($ratio->isEqualTo($minRatio)) {
                        if ($basis[$i] < $basis[$pivotRow]) {
                            $pivotRow = $i;
                        }
                    }
                }
            }

            if ($pivotRow === -1) {
                break;
            }

            $this->pivot($table, $basis, $pivotRow, $pivotCol);
        }
    }

    /**
     * @param  list<list<BigRational>>  $table
     * @param  array<int, int>  $basis
     */
    private function pivot(array &$table, array &$basis, int $pivotRow, int $pivotCol): void
    {
        $numRows = count($table);
        $numCols = count($table[0]);
        $pivotVal = $table[$pivotRow][$pivotCol];

        // Normalisasi baris pivot
        for ($j = 0; $j < $numCols; $j++) {
            $table[$pivotRow][$j] = $table[$pivotRow][$j]->dividedBy($pivotVal);
        }

        // Eliminasi baris lainnya
        for ($i = 0; $i < $numRows; $i++) {
            if ($i === $pivotRow) {
                continue;
            }
            $factor = $table[$i][$pivotCol];
            if (! $factor->isZero()) {
                for ($j = 0; $j < $numCols; $j++) {
                    $table[$i][$j] = $table[$i][$j]->minus($factor->multipliedBy($table[$pivotRow][$j]));
                }
            }
        }

        $basis[$pivotRow] = $pivotCol;
    }
}
