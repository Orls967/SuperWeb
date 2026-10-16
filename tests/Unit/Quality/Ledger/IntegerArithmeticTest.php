<?php

declare(strict_types=1);

it('calculates Resto PB1 with exact integer half-up arithmetic', function (): void {
    // Formula: intdiv($amount * 10 + 50, 100)
    // 15,555 * 10% = 1,555.5 -> 1,556
    $pb1A = intdiv(15_555 * 10 + 50, 100);
    expect($pb1A)->toBe(1556);

    // 15,554 * 10% = 1,555.4 -> 1,555
    $pb1B = intdiv(15_554 * 10 + 50, 100);
    expect($pb1B)->toBe(1555);

    // 45,750 * 10% = 4,575.0 -> 4,575
    $pb1C = intdiv(45_750 * 10 + 50, 100);
    expect($pb1C)->toBe(4575);
});

it('calculates HCM payroll deductions and labor allocation using integer arithmetic', function (): void {
    $gross = 10_000_000;
    $bpjsTk = intdiv($gross * 3 + 50, 100);
    $bpjsKes = intdiv($gross * 1 + 50, 100);
    $pph21 = intdiv($gross * 5 + 50, 100);

    expect($bpjsTk)->toBe(300_000);
    expect($bpjsKes)->toBe(100_000);
    expect($pph21)->toBe(500_000);
    expect($gross - ($bpjsTk + $bpjsKes + $pph21))->toBe(9_100_000);

    // Odd amount test
    $oddGross = 15_555;
    $oddTk = intdiv($oddGross * 3 + 50, 100);
    $oddKes = intdiv($oddGross * 1 + 50, 100);
    $oddPph = intdiv($oddGross * 5 + 50, 100);

    expect($oddTk)->toBe(467);
    expect($oddKes)->toBe(156);
    expect($oddPph)->toBe(778);

    // Labor allocation
    $hoursWorked = 7.5;
    $hourlyRateIdr = 40_000;
    $allocatedCost = intdiv((int) round($hoursWorked * 100) * $hourlyRateIdr, 100);
    expect($allocatedCost)->toBe(300_000);
});
