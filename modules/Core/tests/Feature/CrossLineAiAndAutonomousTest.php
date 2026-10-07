<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\CrossLineAiAndAutonomousService;
use Modules\Core\Domain\Models\CoreAiGovernanceDriftLog;

uses(RefreshDatabase::class);

test('(a) rekonstruksi keputusan AI deterministik identik dengan seed dan inputs sama', function () {
    $service = new CrossLineAiAndAutonomousService;

    $inputs = ['current_inventory' => 20, 'competitor_price' => 150000, 'demand_index' => 1.4];

    $dec1 = $service->makeDeterministicDecision('PRICING_OPTIMIZER', 'v2.1', 'SEED-OCT-2026', $inputs);
    $dec2 = $service->makeDeterministicDecision('PRICING_OPTIMIZER', 'v2.1', 'SEED-OCT-2026', $inputs);

    expect($dec1->decision_output)->toBe($dec2->decision_output)
        ->and($dec1->decision_hash)->toBe($dec2->decision_hash)
        ->and(strlen($dec1->decision_hash))->toBe(64);
});

test('(b) kill-switch menghentikan autonomous execution untuk lini terkait', function () {
    $service = new CrossLineAiAndAutonomousService;

    // Normal execution before kill switch
    $op1 = $service->executeAutonomousAction('LINE_13_ENERGY', 3, 'AUTO_DISPATCH_BESS_BATTERY');
    expect($op1->execution_status)->toBe('EXECUTED');

    // Trigger emergency kill-switch
    $service->activateKillSwitch('LINE_13_ENERGY');

    // Subsequent execution blocked immediately
    expect(fn () => $service->executeAutonomousAction('LINE_13_ENERGY', 3, 'AUTO_DISPATCH_BESS_BATTERY'))
        ->toThrow(RuntimeException::class, 'Kill-switch is active');
});

test('(d) drift check terjadwal mencatat drift score dan mendeteksi kebutuhan retraining', function () {
    $service = new CrossLineAiAndAutonomousService;

    // Normal drift (no retraining needed)
    $logNormal = $service->runScheduledDriftCheck('CHURN_PREDICTOR', 0.10, 0.08);
    expect($logNormal)->toBeInstanceOf(CoreAiGovernanceDriftLog::class)
        ->and($logNormal->requires_retraining)->toBeFalse();

    // High drift (retraining triggered)
    $logDrifted = $service->runScheduledDriftCheck('CHURN_PREDICTOR', 0.35, 0.28);
    expect($logDrifted->requires_retraining)->toBeTrue();
});
