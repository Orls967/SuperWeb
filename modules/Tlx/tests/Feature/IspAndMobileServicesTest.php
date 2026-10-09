<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Tlx\Application\Services\IspAndMobileServices;
use Modules\Tlx\Domain\Models\InterconnectSettlement;
use Modules\Tlx\Domain\Models\SmartCityService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $accounts = [
        'tlx:interconnect_clearing:IDR' => 'asset',
        'tlx:interconnect_net_settlement:IDR' => 'liability',
    ];

    foreach ($accounts as $code => $kind) {
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'kind' => $kind,
                'asset_code' => 'IDR',
                'allow_negative' => true,
                'cached_balance' => '0',
                'name' => "Telecom {$code}",
            ]
        );
    }
});

test('(a) auto-renew gagal saldo wallet -> layanan pause, bukan gratis', function () {
    $service = app(IspAndMobileServices::class);

    $sim = $service->registerSimSubscriber([
        'msisdn' => '081122334455',
        'iccid' => '8962000012345678',
        'account_tier' => 'PARENT',
        'wallet_id' => 'WALLET-USER-001',
        'plan_code' => '5G-SUPER-50GB',
        'quota_allowance_gb' => 50.0,
        'auto_renew_fee_minor' => 15000000, // 150,000 IDR
    ]);

    expect($sim->status)->toBe('ACTIVE');

    // Renewal attempt with insufficient wallet balance
    $pausedSim = $service->autoRenewSimPlan($sim->msisdn, 5000000); // only 50k available
    expect($pausedSim->status)->toBe('PAUSED');

    // Renewal attempt with sufficient wallet balance -> restored with rollover
    $renewedSim = $service->autoRenewSimPlan($sim->msisdn, 20000000); // 200k available
    expect($renewedSim->status)->toBe('ACTIVE')
        ->and($renewedSim->quota_remaining_gb)->toBeGreaterThanOrEqual(50.0);
});

test('(b) ISP usage cap dihormati dan overdue menangguhkan layanan', function () {
    $service = app(IspAndMobileServices::class);

    $isp = $service->createIspSubscription([
        'customer_id' => 'CUST-RESIDENTIAL-88',
        'plan_type' => 'HOME_BROADBAND',
        'speed_mbps' => 100.0,
        'monthly_usage_cap_gb' => 500.0,
        'monthly_fee_minor' => 35000000,
        'billing_type' => 'POSTPAID',
    ]);

    expect($isp->service_status)->toBe('ACTIVE');

    // Consumed within cap
    $isp = $service->recordIspUsage($isp->id, 450.0);
    expect($isp->service_status)->toBe('ACTIVE');

    // Consumed beyond cap -> throttled
    $isp = $service->recordIspUsage($isp->id, 60.0);
    expect($isp->service_status)->toBe('THROTTLED');

    // Overdue -> paused
    $pausedIsp = $service->setIspOverdueStatus($isp->id, true);
    expect($pausedIsp->service_status)->toBe('PAUSED');
});

test('(c) interconnect settlement antar operator seimbang di ledger dan terhitung akurat', function () {
    $service = app(IspAndMobileServices::class);

    $settlement = $service->settleInterconnect([
        'partner_operator_code' => 'TELKOM_ID',
        'period_month' => '2026-10',
        'inbound_minutes' => 1000000, // 1M minutes
        'outbound_minutes' => 800000,  // 800k minutes
        'rate_per_minute_minor' => 25000, // 250 IDR/min
    ]);

    expect($settlement)->toBeInstanceOf(InterconnectSettlement::class)
        ->and($settlement->inbound_receivable_minor)->toBe(25000000000)
        ->and($settlement->outbound_payable_minor)->toBe(20000000000)
        ->and($settlement->net_settlement_minor)->toBe(5000000000) // 5B IDR net receivable
        ->and($settlement->status)->toBe('SETTLED');
});

test('(d) smart city IoT service contract terdaftar dan terpantau', function () {
    $service = app(IspAndMobileServices::class);

    $sc = $service->registerSmartCityService([
        'municipality_name' => 'DKI Jakarta Dishub',
        'service_type' => 'SMART_PARKING',
        'active_sensor_count' => 1250,
        'sla_target_pct' => 99.95,
        'sla_achieved_pct' => 99.98,
        'monthly_contract_value_minor' => 15000000000,
    ]);

    expect($sc)->toBeInstanceOf(SmartCityService::class)
        ->and($sc->sla_achieved_pct)->toBe(99.98)
        ->and($sc->active_sensor_count)->toBe(1250);
});

test('(e) churn prediction deterministik berdasarkan pola penurunan utilisasi dan tiket', function () {
    $service = app(IspAndMobileServices::class);

    // High decline (80%) + 4 tickets -> Critical
    $criticalChurn = $service->calculateChurnRisk('SUB-001', 'ISP', 80.0, 4);
    expect($criticalChurn->risk_level)->toBe('CRITICAL')
        ->and($criticalChurn->recommended_action)->toBe('PROACTIVE_CALL')
        ->and($criticalChurn->churn_risk_score)->toBeGreaterThanOrEqual(0.75);

    // Low decline (10%) + 0 tickets -> Low
    $lowChurn = $service->calculateChurnRisk('SUB-002', 'MOBILE', 10.0, 0);
    expect($lowChurn->risk_level)->toBe('LOW')
        ->and($lowChurn->recommended_action)->toBe('STANDARD_NURTURE');
});
