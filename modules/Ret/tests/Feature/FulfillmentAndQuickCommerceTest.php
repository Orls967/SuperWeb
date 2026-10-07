<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Ret\Application\Services\FulfillmentAndQuickCommerceService;
use Modules\Ret\Domain\Models\RetCrowdshippingTask;
use Modules\Ret\Domain\Models\RetQuickCommerceOrder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $accounts = [
        'ret:sla_compensation_expense:IDR' => 'expense',
        'ret:customer_credit_liability:IDR' => 'liability',
        'ret:packaging_deposit_cash:IDR' => 'asset',
        'ret:packaging_deposit_liability:IDR' => 'liability',
    ];

    foreach ($accounts as $code => $kind) {
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'kind' => $kind,
                'asset_code' => 'IDR',
                'allow_negative' => true,
                'cached_balance' => '0',
                'name' => "Retail {$code}",
            ]
        );
    }
});

test('(a), (d) promise breach -> kredit otomatis 1x, picking time tercatat', function () {
    $service = app(FulfillmentAndQuickCommerceService::class);

    $placedAt = Carbon::parse('2026-10-08 14:00:00');
    $order = $service->createQuickCommerceOrder([
        'order_code' => 'QC-2026-001',
        'dark_store_id' => 'DARKSTORE-SOUTH-JKT-01',
        'customer_id' => 'CUST-HUNGRY-01',
        'placed_at' => $placedAt,
        'picking_duration_seconds' => 145, // picking took 145s
    ]);

    expect($order)->toBeInstanceOf(RetQuickCommerceOrder::class)
        ->and($order->picking_duration_seconds)->toBe(145)
        ->and($order->is_sla_breached)->toBeFalse();

    // Delivered late (arrived at 14:38:00 > 14:30:00 promise) -> Breach & 1x credit compensation
    $lateArrival = Carbon::parse('2026-10-08 14:38:00');
    $breachedOrder = $service->recordDeliveryArrival($order->order_code, $lateArrival);

    expect($breachedOrder->is_sla_breached)->toBeTrue()
        ->and($breachedOrder->compensation_issued)->toBeTrue()
        ->and($breachedOrder->auto_credit_compensation_minor)->toBe(2500000);

    // Subsequent arrival call does not double-issue compensation (idempotent)
    $repeatOrder = $service->recordDeliveryArrival($order->order_code, $lateArrival);
    expect($repeatOrder->auto_credit_compensation_minor)->toBe(2500000);
});

test('(b) deposit kemasan circulating count * deposit amount = total circulating deposits', function () {
    $service = app(FulfillmentAndQuickCommerceService::class);

    $dep1 = $service->issueReusablePackaging('CUST-GREEN-01', 'INSULATED_TOTE', 5000000); // 50k IDR
    $dep2 = $service->issueReusablePackaging('CUST-GREEN-02', 'GLASS_CONTAINER', 3000000); // 30k IDR

    expect($service->calculateTotalCirculatingDeposits())->toBe(8000000);

    // Customer 1 returns packaging -> deposit refunded
    $refunded = $service->returnReusablePackaging($dep1->deposit_code);
    expect($refunded->status)->toBe('RETURNED_REFUNDED');

    // Remaining circulating deposits
    expect($service->calculateTotalCirculatingDeposits())->toBe(3000000);
});

test('(c) crowdshipper fee <= 30% order value rules dihormati dan hash POD tercatat', function () {
    $service = app(FulfillmentAndQuickCommerceService::class);

    // Order value: 200,000 IDR (20,000,000 minor)
    // Fee 40,000 IDR (4,000,000 minor) = 20% <= 30% -> Accepted
    $task = $service->assignCrowdshipping([
        'order_code' => 'QC-2026-002',
        'crowd_driver_id' => 'DRIVER-CROWD-55',
        'order_value_minor' => 20000000,
        'delivery_fee_minor' => 4000000,
    ]);

    expect($task)->toBeInstanceOf(RetCrowdshippingTask::class)
        ->and($task->delivery_fee_minor)->toBe(4000000)
        ->and(strlen($task->pod_hash))->toBe(64);

    // Fee 80,000 IDR (8,000,000 minor) = 40% > 30% -> Exception
    expect(fn () => $service->assignCrowdshipping([
        'order_code' => 'QC-2026-003',
        'crowd_driver_id' => 'DRIVER-CROWD-55',
        'order_value_minor' => 20000000,
        'delivery_fee_minor' => 8000000,
    ]))->toThrow(RuntimeException::class, 'exceeds maximum allowed rule');
});
