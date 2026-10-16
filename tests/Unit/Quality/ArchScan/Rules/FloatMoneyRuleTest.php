<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\FloatMoneyRule;
use App\Quality\Support\SourceFile;

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

it('does not report decimal idr or double usd columns when remediated by newer alter migration in same module', function (): void {
    $tempDir = sys_get_temp_dir().'/float_money_test_'.uniqid();
    mkdir($tempDir, 0777, true);

    $createPath = $tempDir.'/2026_01_01_000000_create_test_tables.php';
    $alterPath = $tempDir.'/2026_01_02_000000_alter_test_tables.php';

    try {
        $createCode = <<<'PHP'
        <?php
        Schema::create('test_table', function (Blueprint $table) {
            $table->decimal('price_idr', 18, 2);
            $table->double('base_price_usd');
            $table->decimal('unremediated_price_idr', 18, 2);
        });
        PHP;

        $alterCode = <<<'PHP'
        <?php
        Schema::table('test_table', function (Blueprint $table) {
            $table->bigInteger('price_idr')->change();
            $table->decimal('base_price_usd', 14, 2)->change();
        });
        PHP;

        file_put_contents($createPath, $createCode);
        file_put_contents($alterPath, $alterCode);

        $sourceFile = new SourceFile(
            'modules/Test/database/migrations/2026_01_01_000000_create_test_tables.php',
            $createCode,
            $createPath,
        );

        $rule = new FloatMoneyRule;
        $violations = $rule->check($sourceFile, qualityTestRegistry());
        $signatures = array_map(static fn ($v): string => $v->signature, $violations);

        expect($signatures)->toBe([
            "column decimal('unremediated_price_idr')",
        ]);
    } finally {
        @unlink($createPath);
        @unlink($alterPath);
        @rmdir($tempDir);
    }
});
