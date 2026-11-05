<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\LifeContingency;

use InvalidArgumentException;

/**
 * Algoritma A7: Kalkulasi Premi Aktuaria Asuransi Jiwa Berjangka (Term Life Contingency).
 * Menggunakan Tabel Mortalitas Deterministik (TMI IV Benchmark), faktor diskonto bunga konstan,
 * dan fungsi komutasi kanonikal Dx, Nx, Cx, Mx.
 */
final class TermLifePremium
{
    /**
     * Hitung Net Level Annual Premium dan Net Single Premium untuk Term Life n-tahun.
     *
     * @param  int  $age  Usia masuk tertanggung (18 - 70)
     * @param  int  $termYears  Jangka waktu pertanggungan tahunan (1 - 40)
     * @param  int  $sumInsuredIdr  Uang Pertanggungan (minor unit IDR)
     * @param  int  $interestRateBps  Tingkat bunga aktuaria tahunan dalam basis poin (misal 500 = 5.0%)
     */
    public function calculate(
        int $age,
        int $termYears,
        int $sumInsuredIdr,
        int $interestRateBps = 500,
    ): TermLifePremiumResult {
        if ($age < 18 || $age > 75) {
            throw new InvalidArgumentException("Usia masuk tertanggung {$age} di luar batas (18 - 75 tahun).");
        }
        if ($termYears < 1 || ($age + $termYears) > 95) {
            throw new InvalidArgumentException("Jangka waktu pertanggungan {$termYears} tahun melebihi batas usia maksimal (95 tahun).");
        }
        if ($sumInsuredIdr <= 0) {
            throw new InvalidArgumentException('Uang pertanggungan harus bernilai positif.');
        }
        if ($interestRateBps <= 0 || $interestRateBps > 2000) {
            throw new InvalidArgumentException('Tingkat bunga aktuaria di luar batas wajar (0 - 2000 bps).');
        }

        $i = $interestRateBps / 10000.0;
        $v = 1.0 / (1.0 + $i);

        $maxAge = 100;
        $commutation = [];

        // Inisialisasi radix l0 = 100,000
        $lx = 100000.0;
        for ($x = 0; $x <= $maxAge; $x++) {
            $qx = $this->mortalityRate($x);
            $dx = $lx * $qx;
            $px = 1.0 - $qx;

            // Dx = v^x * lx
            $vPowX = pow($v, $x);
            $dxComm = $vPowX * $lx;

            // Cx = v^(x+1) * dx
            $cx = pow($v, $x + 1) * $dx;

            $commutation[$x] = [
                'age' => $x,
                'lx' => $lx,
                'dx' => $dx,
                'qx' => $qx,
                'px' => $px,
                'cx' => $cx,
                'mx' => 0.0,
                'dx_comm' => $dxComm,
                'nx_comm' => 0.0,
            ];

            $lx = max(0.0, $lx - $dx);
        }

        // Hitung akumulasi backward untuk Nx dan Mx
        $runningNx = 0.0;
        $runningMx = 0.0;
        for ($x = $maxAge; $x >= 0; $x--) {
            $runningNx += $commutation[$x]['dx_comm'];
            $runningMx += $commutation[$x]['cx'];

            $commutation[$x]['nx_comm'] = $runningNx;
            $commutation[$x]['mx'] = $runningMx;
        }

        $x0 = $age;
        $xEnd = $age + $termYears;

        $mx0 = $commutation[$x0]['mx'];
        $mxEnd = $commutation[$xEnd]['mx'] ?? 0.0;
        $dx0 = $commutation[$x0]['dx_comm'];
        $nx0 = $commutation[$x0]['nx_comm'];
        $nxEnd = $commutation[$xEnd]['nx_comm'] ?? 0.0;

        // Ax:n^1 = (Mx - Mx+n) / Dx
        $netSingleRate = ($dx0 > 0.0) ? (($mx0 - $mxEnd) / $dx0) : 0.0;
        $netSinglePremium = (int) round($sumInsuredIdr * $netSingleRate);

        // ax:n = (Nx - Nx+n) / Dx
        $annuityDue = ($dx0 > 0.0) ? (($nx0 - $nxEnd) / $dx0) : 1.0;
        $annualNetRate = ($annuityDue > 0.0) ? ($netSingleRate / $annuityDue) : $netSingleRate;
        $annualNetPremium = (int) round($sumInsuredIdr * $annualNetRate);

        // Sertifikat hash kanonikal
        $canonicalPayload = sprintf(
            'TERMLIFE:A7|AGE:%d|TERM:%d|SI:%d|RATE:%d|NSP:%d|ANN:%d|MX0:%.4f|NX0:%.4f',
            $age,
            $termYears,
            $sumInsuredIdr,
            $interestRateBps,
            $netSinglePremium,
            $annualNetPremium,
            $mx0,
            $nx0
        );
        $certHash = hash('sha256', $canonicalPayload);

        return new TermLifePremiumResult(
            annualNetPremiumIdr: $annualNetPremium,
            netSinglePremiumIdr: $netSinglePremium,
            sumInsuredIdr: $sumInsuredIdr,
            age: $age,
            termYears: $termYears,
            interestRateBps: $interestRateBps,
            commutationTable: array_slice($commutation, $age, $termYears + 1, true),
            certificateHash: $certHash
        );
    }

    /**
     * Tabel Mortalitas Standar Deterministik (TMI IV / Indonesian Mortality Table).
     */
    private function mortalityRate(int $age): float
    {
        if ($age < 20) {
            return 0.00065;
        }
        if ($age < 30) {
            return 0.00075 + (($age - 20) * 0.00003);
        }
        if ($age < 40) {
            return 0.00110 + (($age - 30) * 0.00007);
        }
        if ($age < 50) {
            return 0.00200 + (($age - 40) * 0.00018);
        }
        if ($age < 60) {
            return 0.00450 + (($age - 50) * 0.00045);
        }
        if ($age < 70) {
            return 0.01100 + (($age - 60) * 0.00120);
        }
        if ($age < 80) {
            return 0.02800 + (($age - 70) * 0.00350);
        }
        if ($age < 90) {
            return 0.07500 + (($age - 80) * 0.00900);
        }

        return min(1.0, 0.18000 + (($age - 90) * 0.03500));
    }
}
