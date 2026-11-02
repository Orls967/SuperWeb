<?php

declare(strict_types=1);

use Brick\Math\BigRational;
use Modules\Shared\Domain\Algorithms\Lp\LpCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Lp\LpResult;
use Modules\Shared\Domain\Algorithms\Lp\LpStatus;
use Modules\Shared\Domain\Algorithms\Lp\RationalSimplex;

covers(RationalSimplex::class, LpCertificateVerifier::class, LpResult::class);

describe('RationalSimplex & LpCertificateVerifier', function (): void {
    test('vektor T-4 optimasi blending batubara menghasilkan solusi eksak dan sertifikat dual valid', function (): void {
        $simplex = new RationalSimplex;

        // Stockpile A: 4.200 kcal, abu 800 bps, stok 30.000 t
        // Stockpile B: 5.000 kcal, abu 1.400 bps, stok 20.000 t
        // Target: kalori >= 4.600, abu <= 1.100, 10.000 <= total <= 40.000
        // max xA + xB s.t.
        // xA <= 30000
        // xB <= 20000
        // xA + xB <= 40000
        // -xA - xB <= -10000
        // 400 xA - 400 xB <= 0  (kalori >= 4600)
        // -300 xA + 300 xB <= 0 (abu <= 1100)
        $c = [1, 1];
        $A = [
            [1, 0],
            [0, 1],
            [1, 1],
            [-1, -1],
            [400, -400],
            [-300, 300],
        ];
        $b = [30000, 20000, 40000, -10000, 0, 0];

        $result = $simplex->solve($c, $A, $b);

        expect($result->status)->toBe(LpStatus::OPTIMAL)
            ->and($result->objectiveValue->toInt())->toBe(40000)
            ->and($result->x[0]->toInt())->toBe(20000)
            ->and($result->x[1]->toInt())->toBe(20000);

        // Verifikasi sertifikat dual PR2
        $verifier = new LpCertificateVerifier;
        $cR = array_map(fn ($v) => BigRational::of($v), $c);
        $AR = array_map(fn ($r) => array_map(fn ($v) => BigRational::of($v), $r), $A);
        $bR = array_map(fn ($v) => BigRational::of($v), $b);

        expect($verifier->verify($cR, $AR, $bR, $result))->toBeTrue();
    });

    test('vektor T-4 infeasible menghasilkan status INFEASIBLE dan sertifikat Farkas valid', function (): void {
        $simplex = new RationalSimplex;

        // Target kalori >= 5.100 kcal dengan tonase minimum 10.000 t (mustahil karena max kalori sumber = 5.000)
        $c = [1, 1];
        $A = [
            [1, 0],
            [0, 1],
            [1, 1],
            [-1, -1],
            [900, 100], // 4200 xA + 5000 xB >= 5100 (xA + xB) => 900 xA + 100 xB <= 0
        ];
        $b = [30000, 20000, 40000, -10000, 0];

        $result = $simplex->solve($c, $A, $b);

        expect($result->status)->toBe(LpStatus::INFEASIBLE)
            ->and($result->x)->toBeEmpty()
            ->and($result->objectiveValue)->toBeNull()
            ->and($result->y)->toHaveCount(5);

        // Verifikasi Farkas PR2
        $verifier = new LpCertificateVerifier;
        $cR = array_map(fn ($v) => BigRational::of($v), $c);
        $AR = array_map(fn ($r) => array_map(fn ($v) => BigRational::of($v), $r), $A);
        $bR = array_map(fn ($v) => BigRational::of($v), $b);

        expect($verifier->verify($cR, $AR, $bR, $result))->toBeTrue();
    });

    test('mendeteksi persoalan tak terbatas (unbounded)', function (): void {
        $simplex = new RationalSimplex;

        // Max x1 + x2 s.t. x1 - x2 <= 1, x >= 0 (x2 can grow arbitrarily large)
        $c = [1, 1];
        $A = [
            [1, -1],
        ];
        $b = [1];

        $result = $simplex->solve($c, $A, $b);

        expect($result->status)->toBe(LpStatus::UNBOUNDED)
            ->and($result->objectiveValue)->toBeNull();
    });

    test('menolak input melebihi batas variabel atau batasan', function (): void {
        $simplex = new RationalSimplex;

        // Lebih dari 30 variabel
        $c = array_fill(0, 31, 1);
        $A = [array_fill(0, 31, 1)];
        $b = [10];

        expect(fn () => $simplex->solve($c, $A, $b))
            ->toThrow(InvalidArgumentException::class, 'Jumlah variabel (31) melebihi batas maksimum 30');

        // Lebih dari 20 batasan
        $c2 = [1, 1];
        $A2 = array_fill(0, 21, [1, 1]);
        $b2 = array_fill(0, 21, 10);

        expect(fn () => $simplex->solve($c2, $A2, $b2))
            ->toThrow(InvalidArgumentException::class, 'Jumlah batasan (21) melebihi batas maksimum 20');

        // Dimensi tidak cocok
        expect(fn () => $simplex->solve([1, 1], [[1, 1]], [10, 20]))
            ->toThrow(InvalidArgumentException::class, 'Jumlah elemen b (2) tidak cocok');

        expect(fn () => $simplex->solve([1, 1], [[1]], [10]))
            ->toThrow(InvalidArgumentException::class, 'harus memiliki panjang n = 2');
    });

    test('menerima tepat 30 variabel dan 20 batasan (batas maksimum eksak)', function (): void {
        $simplex = new RationalSimplex;
        // 30 variabel, 20 variabel pertama berbobot 1, 10 sisanya 0
        $c = array_merge(array_fill(0, 20, BigRational::one()), array_fill(0, 10, BigRational::zero()));
        $A = array_fill(0, 20, array_fill(0, 30, BigRational::zero()));
        for ($i = 0; $i < 20; $i++) {
            $A[$i][$i] = BigRational::one();
        }
        $b = array_fill(0, 20, BigRational::of(10));

        $res = $simplex->solve($c, $A, $b);
        expect($res->status)->toBe(LpStatus::OPTIMAL)
            ->and($res->objectiveValue->toInt())->toBe(200);
    });

    test('menangani kasus trivial matriks kosong atau sebagian kosong', function (): void {
        $simplex = new RationalSimplex;

        $result = $simplex->solve([], [], []);
        expect($result->status)->toBe(LpStatus::OPTIMAL)
            ->and($result->objectiveValue->toInt())->toBe(0);

        $res1 = $simplex->solve([BigRational::zero()], [], []);
        expect($res1->status)->toBe(LpStatus::OPTIMAL)
            ->and($res1->objectiveValue->toInt())->toBe(0);

        $res2 = $simplex->solve([], [[], []], [BigRational::zero(), BigRational::zero()]);
        expect($res2->status)->toBe(LpStatus::OPTIMAL)
            ->and($res2->objectiveValue->toInt())->toBe(0);
    });

    test('tie break pada fase 1 dengan baris rasio identik', function (): void {
        $simplex = new RationalSimplex;
        $c = [BigRational::one()];
        $A = [[BigRational::of(-1)], [BigRational::of(-1)]];
        $b = [BigRational::of(-5), BigRational::of(-5)];
        $res = $simplex->solve($c, $A, $b);
        expect($res->status)->toBe(LpStatus::UNBOUNDED);
    });

    test('aturan Bland mencegah cycling pada contoh klasik Beale', function (): void {
        $simplex = new RationalSimplex;

        // Masalah klasik Beale yang siklus bila memakai aturan pivot Dantzig:
        // Max 10 x1 - 57 x2 - 9 x3 - 24 x4
        // s.t.
        // 0.5 x1 - 5.5 x2 - 2.5 x3 + 9 x4 <= 0
        // 0.5 x1 - 1.5 x2 - 0.5 x3 + 1 x4 <= 0
        // 1 x1 <= 1
        $c = [
            BigRational::of(10),
            BigRational::of(-57),
            BigRational::of(-9),
            BigRational::of(-24),
        ];
        $A = [
            [BigRational::of('1/2'), BigRational::of('-11/2'), BigRational::of('-5/2'), BigRational::of(9)],
            [BigRational::of('1/2'), BigRational::of('-3/2'), BigRational::of('-1/2'), BigRational::of(1)],
            [BigRational::one(), BigRational::zero(), BigRational::zero(), BigRational::zero()],
        ];
        $b = [BigRational::zero(), BigRational::zero(), BigRational::one()];

        $result = $simplex->solve($c, $A, $b);

        expect($result->status)->toBe(LpStatus::OPTIMAL)
            ->and($result->objectiveValue)->not->toBeNull();
    });

    test('verifier melempar exception bila sertifikat optimal atau Farkas dimanipulasi', function (): void {
        $verifier = new LpCertificateVerifier;
        $c = [BigRational::one(), BigRational::one()];
        $A = [[BigRational::one(), BigRational::zero()], [BigRational::zero(), BigRational::one()]];
        $b = [BigRational::of(10), BigRational::of(10)];

        // Kasus 1: x negatif
        $tamperedResult = new LpResult(
            LpStatus::OPTIMAL,
            [BigRational::of(-1), BigRational::of(10)],
            BigRational::of(9),
            [BigRational::one(), BigRational::one()]
        );
        expect(fn () => $verifier->verify($c, $A, $b, $tamperedResult))
            ->toThrow(RuntimeException::class, 'Pelanggaran kelayakan primal');

        // Kasus 2: A*x > b
        $tamperedResult2 = new LpResult(
            LpStatus::OPTIMAL,
            [BigRational::of(15), BigRational::of(10)],
            BigRational::of(25),
            [BigRational::one(), BigRational::one()]
        );
        expect(fn () => $verifier->verify($c, $A, $b, $tamperedResult2))
            ->toThrow(RuntimeException::class, 'Pelanggaran batasan primal baris');

        // Kasus 3: y negatif
        $tamperedResult3 = new LpResult(
            LpStatus::OPTIMAL,
            [BigRational::of(10), BigRational::of(10)],
            BigRational::of(20),
            [BigRational::of(-1), BigRational::one()]
        );
        expect(fn () => $verifier->verify($c, $A, $b, $tamperedResult3))
            ->toThrow(RuntimeException::class, 'Pelanggaran kelayakan dual');

        // Kasus 4: dualitas kuat terlanggar
        $tamperedResult4 = new LpResult(
            LpStatus::OPTIMAL,
            [BigRational::of(10), BigRational::of(10)],
            BigRational::of(25), // bohong, c*x = 20
            [BigRational::one(), BigRational::one()]
        );
        expect(fn () => $verifier->verify($c, $A, $b, $tamperedResult4))
            ->toThrow(RuntimeException::class, 'tidak sama dengan objectiveValue');

        // Kasus 5: Farkas b^T y >= 0
        $tamperedFarkas = new LpResult(
            LpStatus::INFEASIBLE,
            [],
            null,
            [BigRational::zero(), BigRational::zero()]
        );
        expect(fn () => $verifier->verify($c, $A, $b, $tamperedFarkas))
            ->toThrow(RuntimeException::class, 'Sertifikat Farkas tidak valid: b^T*y');
    });

    test('benchmark skala rational simplex 10 variabel dan 10 batasan < 1.0s', function (): void {
        $simplex = new RationalSimplex;

        $n = 10;
        $m = 10;
        $c = array_fill(0, $n, 5);
        $A = [];
        for ($i = 0; $i < $m; $i++) {
            $row = [];
            for ($j = 0; $j < $n; $j++) {
                $row[] = ($i === $j) ? 2 : 1;
            }
            $A[] = $row;
        }
        $b = array_fill(0, $m, 100);

        $start = microtime(true);
        $result = $simplex->solve($c, $A, $b);
        $elapsed = microtime(true) - $start;

        expect($result->status)->toBe(LpStatus::OPTIMAL)
            ->and($elapsed)->toBeLessThan(1.0);
    })->group('skala');
});
