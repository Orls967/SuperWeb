<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\FloatMoneyRule;

it('reports float money parameters, promoted properties and properties', function (): void {
    $code = <<<'PHP'
        <?php
        final class Billing
        {
            private ?float $balance = null;
            public function __construct(public readonly float $amountIdr) {}
            public function charge(float $unitPrice, int $quantity, float $costPerDay): void {}
        }
        PHP;

    $signatures = ruleSignatures(new FloatMoneyRule, 'modules/Hotel/Application/Services/Billing.php', $code);

    expect($signatures)->toBe([
        'param float $amountIdr @ __construct()',
        'param float $unitPrice @ charge()',
        'param float $costPerDay @ charge()',
        'property float $balance',
    ]);
});

it('does not report integer money, physical quantities, rates or latency budgets', function (): void {
    $code = <<<'PHP'
        <?php
        final class Meter
        {
            public function record(int $amountIdr, float $totalKwh, float $feeRate, float $maxP99BudgetMs, float $totalHours): void {}
        }
        PHP;

    $signatures = ruleSignatures(new FloatMoneyRule, 'modules/Egy/Application/Services/Meter.php', $code);

    expect($signatures)->toBe([]);
});

it('reports IDR stored as decimal and money stored as float in migrations', function (): void {
    $code = <<<'PHP'
        <?php
        Schema::create('htl_folios', function (Blueprint $table) {
            $table->decimal('total_idr', 18, 2);
            $table->float('room_price');
            $table->decimal('fx_rate', 18, 6);
            $table->bigInteger('deposit_idr');
            $table->double('weight_kg');
        });
        PHP;

    $signatures = ruleSignatures(new FloatMoneyRule, 'modules/Hotel/database/migrations/2026_01_01_000000_create_folios.php', $code);

    expect($signatures)->toBe([
        "column decimal('total_idr')",
        "column float('room_price')",
    ]);
});

it('classifies names as money or not', function (string $name, bool $isMoney): void {
    expect(FloatMoneyRule::isMoneyName($name))->toBe($isMoney);
})->with([
    'explicit idr' => ['hourlyRateIdr', true],
    'unit price' => ['unit_price', true],
    'cost per unit of time' => ['costPerDay', true],
    'total revenue' => ['totalRevenue', true],
    'energy quantity' => ['totalKwh', false],
    'fee rate' => ['feeRate', false],
    'latency budget' => ['maxP99BudgetMs', false],
    'unrelated' => ['temperatureCelsius', false],
]);
