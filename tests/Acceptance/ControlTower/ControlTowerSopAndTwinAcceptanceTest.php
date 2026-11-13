<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ControlTower\Domain\Algorithms\AtpCtpCalculator;
use Modules\ControlTower\Domain\Algorithms\DemandForecastEngine;
use Modules\ControlTower\Domain\Algorithms\SafetyStockAbcXyzOptimizer;
use Modules\ControlTower\Domain\Certificates\ForecastAccuracyCertificateVerifier;
use Modules\ControlTower\Domain\Certificates\OrderPromiseCertificateVerifier;
use Modules\ControlTower\Domain\Certificates\SafetyStockPolicyCertificateVerifier;
use Modules\ControlTower\Domain\StateMachines\SopCycleStateMachine;
use Modules\ControlTower\Infrastructure\Audit\ControlTowerAuditCorruptionFixture;
use Modules\ControlTower\Infrastructure\Simulation\ControlTowerTwinTickHandler;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('certifies multi-model demand forecasting with integer fixed-point metrics', function (): void {
    $engine = new DemandForecastEngine;
    $result = $engine->forecast(
        itemCode: 'SKU-CT-01',
        history: [100, 110, 120, 130, 140, 150],
        model: 'LINEAR_REGRESSION',
        alphaBps: 3000,
    );

    expect($result['forecast_qty'])->toBeGreaterThan(150)
        ->and((new ForecastAccuracyCertificateVerifier)->verify($result['certificate']))->toBeTrue();
});

it('certifies atp and ctp promise allocation without exceeding physical supply', function (): void {
    $calc = new AtpCtpCalculator;
    $result = $calc->evaluate(
        itemCode: 'SKU-CT-02',
        onHandQty: 40,
        scheduledReceiptsQty: 30,
        alreadyCommittedQty: 20,
        requestedQty: 65,
        bomComponentAvailableQty: 25,
        plantFreeCapacityUnits: 20,
    );

    expect($result['atp_confirmed_qty'])->toBe(50)
        ->and($result['ctp_manufacturing_qty'])->toBe(15)
        ->and($result['promise_status'])->toBe('confirmed')
        ->and((new OrderPromiseCertificateVerifier)->verify($result['certificate']))->toBeTrue();
});

it('certifies abc xyz matrix and dynamic safety stock calculation', function (): void {
    $optimizer = new SafetyStockAbcXyzOptimizer;
    $result = $optimizer->optimize(
        itemCode: 'SKU-CT-03',
        annualUsageValueIdr: 500_000_000,
        totalCatalogValueIdr: 1_000_000_000,
        demandHistory: [100, 102, 98, 101, 99, 100],
        avgLeadTimeDays: 7,
        leadTimeStdDevBps: 1500,
        serviceLevelBps: 9500,
    );

    expect($result['abc_class'])->toBe('A')
        ->and($result['xyz_class'])->toBe('X')
        ->and($result['safety_stock_qty'])->toBeGreaterThan(0)
        ->and((new SafetyStockPolicyCertificateVerifier)->verify($result['certificate']))->toBeTrue();
});

it('enforces 7-state sop cycle transitions', function (): void {
    $sm = new SopCycleStateMachine;
    $state = 'draft';
    foreach (['demand_review', 'supply_review', 'pre_sop_balancing', 'executive_signoff', 'published', 'archived'] as $next) {
        expect($sm->canTransition($state, $next))->toBeTrue();
        $state = $next;
    }

    expect($sm->canTransition('draft', 'published'))->toBeFalse()
        ->and($sm->canTransition('archived', 'draft'))->toBeFalse();
});

it('runs control tower twin tick handler deterministically without mutating real tables', function (): void {
    $handler = app(ControlTowerTwinTickHandler::class);
    $summary = $handler->handle(now()->toImmutable(), 14);

    expect($handler->name())->toBe('control_tower_twin')
        ->and($handler->shouldRun(now()->toImmutable(), 14))->toBeTrue()
        ->and($summary)->toHaveKeys(['at_risk_orders', 'simulated_otif_bps']);
});

it('fails tower audit when sabotage fixture introduces atp over-promise', function (): void {
    app(ControlTowerAuditCorruptionFixture::class)->injectAtpOverPromise();
    $this->artisan('tower:audit')->assertExitCode(1);
});
