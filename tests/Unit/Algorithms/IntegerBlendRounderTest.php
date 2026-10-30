<?php

declare(strict_types=1);

use Brick\Math\BigRational;
use Modules\Shared\Domain\Algorithms\Lp\IntegerBlendRounder;
use Modules\Shared\Domain\Algorithms\Lp\LpStatus;

covers(IntegerBlendRounder::class);

describe('IntegerBlendRounder', function (): void {
    test('solusi kontinu eksak integer tidak berubah dan berstatus OPTIMAL', function (): void {
        $rounder = new IntegerBlendRounder;

        // Vektor T-4 eksak (skala normal): 20 kg dan 20 kg
        $xRational = [BigRational::of(20), BigRational::of(20)];
        $minTonnage = 10;
        $maxTonnage = 40;
        $caps = [30, 20];
        $ratioConstraints = [
            [400, -400],  // 400 xA - 400 xB <= 0
            [-300, 300], // -300 xA + 300 xB <= 0
        ];

        $res = $rounder->round($xRational, $minTonnage, $maxTonnage, $caps, $ratioConstraints);

        expect($res['status'])->toBe(LpStatus::OPTIMAL)
            ->and($res['x'])->toBe([20, 20])
            ->and($res['totalKg'])->toBe(40);
    });

    test('reparasi greedy deterministik memperbaiki sedikit pelanggaran rasio akibat floor', function (): void {
        $rounder = new IntegerBlendRounder;

        // Solusi kontinu di mana floor menghasilkan sedikit kelebihan pada B:
        // xA = 20.1, xB = 21.9
        // Floor: xA = 20, xB = 21
        // Batasan 2: -300 xA + 300 xB <= 0 => -300(20) + 300(21) = 300 > 0 (terlanggar)
        // Rounder mengurangi 1 kg dari xB sehingga xB = 20
        $xRational = [BigRational::of('201/10'), BigRational::of('219/10')];
        $minTonnage = 39;
        $maxTonnage = 45;
        $caps = [30, 30];
        $ratioConstraints = [
            [400, -400],
            [-300, 300],
        ];

        $res = $rounder->round($xRational, $minTonnage, $maxTonnage, $caps, $ratioConstraints);

        expect($res['status'])->toBe(LpStatus::OPTIMAL)
            ->and($res['x'])->toBe([20, 20])
            ->and($res['totalKg'])->toBe(40);
    });

    test('tie-break deterministik memilih variabel indeks lebih kecil saat kontribusi pelanggaran sama', function (): void {
        $rounder = new IntegerBlendRounder;

        // Batasan homogen: 2 x0 + 2 x1 - 3 x2 <= 0.
        // x = [2, 3, 3] => 2(2) + 2(3) - 3(3) = 4 + 6 - 9 = 1 > 0 (terlanggar sebesar 1).
        // x0 dan x1 sama-sama memiliki koefisien +2 dan bernilai > 0.
        // Tie-break PR3 harus memilih indeks 0 untuk dikurangi dari 2 menjadi 1.
        $res = $rounder->round(
            [BigRational::of(2), BigRational::of(3), BigRational::of(3)],
            5,
            20,
            [10, 10, 10],
            [[2, 2, -3]]
        );

        expect($res['status'])->toBe(LpStatus::OPTIMAL)
            ->and($res['x'])->toBe([1, 3, 3]) // x[0] dikurangi (bukan x[1])
            ->and($res['totalKg'])->toBe(7);
    });

    test('melaporkan TIDAK_LAYAK_SETELAH_PEMBULATAN bila reparasi menurunkan total di bawah batas minimum', function (): void {
        $rounder = new IntegerBlendRounder;

        // Solusi kontinu sangat jauh dari batas rasio
        $xRational = [BigRational::of('63/4'), BigRational::of('97/4')]; // 15.75, 24.25
        $minTonnage = 39;
        $maxTonnage = 40;
        $caps = [30, 30];
        $ratioConstraints = [
            [400, -400],
            [-300, 300], // -300 xA + 300 xB <= 0 => xB <= xA
        ];

        $res = $rounder->round($xRational, $minTonnage, $maxTonnage, $caps, $ratioConstraints);

        expect($res['status'])->toBe(LpStatus::INFEASIBLE_AFTER_ROUNDING)
            ->and($res['x'])->toBe([15, 23])
            ->and($res['totalKg'])->toBe(38);
    });

    test('memotong kelebihan tonase maksimum pada satu variabel', function (): void {
        $rounder = new IntegerBlendRounder;

        $res = $rounder->round([BigRational::of(100)], 10, 80, [100], []);
        expect($res['status'])->toBe(LpStatus::OPTIMAL)
            ->and($res['x'])->toBe([80])
            ->and($res['totalKg'])->toBe(80);
    });

    test('memotong kelebihan tonase maksimum secara deterministik dari indeks tertinggi', function (): void {
        $rounder = new IntegerBlendRounder;

        $xRational = [BigRational::of(100), BigRational::of(100)];
        $minTonnage = 50;
        $maxTonnage = 150; // Total 200 melebihi 150
        $caps = [200, 200];
        $ratioConstraints = [];

        $res = $rounder->round($xRational, $minTonnage, $maxTonnage, $caps, $ratioConstraints);

        expect($res['status'])->toBe(LpStatus::OPTIMAL)
            ->and($res['x'])->toBe([100, 50]) // Kelebihan 50 dipotong dari x[1]
            ->and($res['totalKg'])->toBe(150);

        // Kasus kelebihan melintasi lebih dari 1 variabel
        $xRational2 = [BigRational::of(100), BigRational::of(100), BigRational::of(100)];
        $res2 = $rounder->round($xRational2, 50, 150, [200, 200, 200], []);
        expect($res2['status'])->toBe(LpStatus::OPTIMAL)
            ->and($res2['x'])->toBe([100, 50, 0])
            ->and($res2['totalKg'])->toBe(150);
    });

    test('menjaga nilai floor tidak melebihi kapasitas stockpile', function (): void {
        $rounder = new IntegerBlendRounder;

        // x[0] = 50 tapi cap = 30
        $xRational = [BigRational::of(50), BigRational::of(20)];
        $res = $rounder->round($xRational, 10, 100, [30, 50], []);

        expect($res['status'])->toBe(LpStatus::OPTIMAL)
            ->and($res['x'])->toBe([30, 20])
            ->and($res['totalKg'])->toBe(50);
    });

    test('variabel bernilai 1 kg didekremen ke 0 untuk memenuhi batasan dan mengabaikan variabel bernilai 0', function (): void {
        $rounder = new IntegerBlendRounder;

        // x = [1, 0, 5]
        // Batasan: 2 x0 + 10 x1 - x2 <= 0 => 2(1) + 10(0) - 5 = -3 <= 0 (terpenuhi)
        // Batasan 2: 5 x0 + 10 x1 - x2 <= 0 => 5(1) + 10(0) - 5 = 0 <= 0 (terpenuhi)
        // Sekarang bila x0 = 1.9 => floor 1, x2 = 4.1 => floor 4:
        // Batasan 2: 5(1) + 10(0) - 4 = 1 > 0 (terlanggar!).
        // Koefisien x1 adalah 10 tapi x1 = 0! Jadi x1 tidak boleh dipilih.
        // x0 memiliki koefisien 5 dan x0 = 1 > 0.
        // Rounder harus memilih x0 dan menguranginya dari 1 menjadi 0!
        $xRational = [BigRational::of('19/10'), BigRational::zero(), BigRational::of('41/10')];
        $res = $rounder->round($xRational, 0, 10, [10, 10, 10], [
            [5, 10, -1],
        ]);

        expect($res['status'])->toBe(LpStatus::OPTIMAL)
            ->and($res['x'])->toBe([0, 0, 4])
            ->and($res['totalKg'])->toBe(4);
    });

    test('menangani kasus satu variabel eksak', function (): void {
        $rounder = new IntegerBlendRounder;

        $res = $rounder->round([BigRational::of('15/2')], 5, 10, [20], []);
        expect($res['status'])->toBe(LpStatus::OPTIMAL)
            ->and($res['x'])->toBe([7])
            ->and($res['totalKg'])->toBe(7);
    });

    test('tonase tepat sama dengan tonase maksimum tidak dipotong', function (): void {
        $rounder = new IntegerBlendRounder;

        $res = $rounder->round([BigRational::of(50), BigRational::of(50)], 10, 100, [100, 100], []);
        expect($res['status'])->toBe(LpStatus::OPTIMAL)
            ->and($res['x'])->toBe([50, 50])
            ->and($res['totalKg'])->toBe(100);
    });

    test('menangani kasus input kosong', function (): void {
        $rounder = new IntegerBlendRounder;

        $res1 = $rounder->round([], 0, 100, [], []);
        expect($res1['status'])->toBe(LpStatus::OPTIMAL)
            ->and($res1['x'])->toBe([])
            ->and($res1['totalKg'])->toBe(0);

        $res2 = $rounder->round([], 100, 200, [], []);
        expect($res2['status'])->toBe(LpStatus::INFEASIBLE_AFTER_ROUNDING)
            ->and($res2['x'])->toBe([])
            ->and($res2['totalKg'])->toBe(0);
    });
});
