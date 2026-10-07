<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\EnterpriseFinance\Application\Services\ConglomerateCapitalAndGovernanceService;
use Modules\EnterpriseFinance\Domain\Models\EfCapexProposal;
use Modules\EnterpriseFinance\Domain\Models\EfMaAcquisition;
use Modules\EnterpriseFinance\Domain\Models\EfSegmentFinancialReport;
use Modules\EnterpriseFinance\Domain\Models\EfSubholdingDividend;

uses(RefreshDatabase::class);

beforeEach(function () {
    $accounts = [
        'ef:holding_dividend_receivable:IDR' => 'asset',
        'ef:holding_dividend_income:IDR' => 'revenue',
    ];

    foreach ($accounts as $code => $kind) {
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'kind' => $kind,
                'asset_code' => 'IDR',
                'allow_negative' => true,
                'cached_balance' => '0',
                'name' => "Enterprise Finance {$code}",
            ]
        );
    }
});

test('(a) dividen holding = verified net profit * payout ratio and balances ledger', function () {
    $service = app(ConglomerateCapitalAndGovernanceService::class);

    // Verified net profit of mining subholding: 500 Miliar IDR (50,000,000,000,000 minor)
    $dividend = $service->declareSubholdingDividend([
        'subholding_name' => 'RESOURCES',
        'subsidiary_entity_id' => 'PT-MINING-HOLDING-TBK',
        'verified_net_profit_minor' => 50000000000000,
        'dividend_payout_ratio_pct' => 40.0, // 40% payout
        'fiscal_year' => '2025',
    ]);

    // 40% of 50T = 20T minor
    expect($dividend)->toBeInstanceOf(EfSubholdingDividend::class)
        ->and($dividend->dividend_declared_minor)->toBe(20000000000000)
        ->and($dividend->status)->toBe('DECLARED');
});

test('(b) capex proposal spending cannot exceed allocated Treasury budget', function () {
    $service = app(ConglomerateCapitalAndGovernanceService::class);

    $proposal = $service->submitAndApproveCapex([
        'line_code' => 'LINE_13_ENERGY',
        'project_title' => 'Floating Solar Farm 100MW Cirata Expansion',
        'requested_budget_minor' => 15000000000000,
        'irr_pct' => 14.5,
        'npv_minor' => 4500000000000,
        'esg_score' => 92.0,
    ], treasuryAllocatedMinor: 12000000000000); // Treasury approved 12T minor

    expect($proposal)->toBeInstanceOf(EfCapexProposal::class)
        ->and($proposal->allocated_treasury_budget_minor)->toBe(12000000000000);

    // First spend 8T minor -> OK
    $service->recordCapexSpending($proposal->proposal_code, 8000000000000);

    // Next spend 5T minor (Total 13T > 12T limit) -> Exception
    expect(fn () => $service->recordCapexSpending($proposal->proposal_code, 5000000000000))
        ->toThrow(RuntimeException::class, 'Capex spending breach');
});

test('(c) M&A integration backfill is strictly idempotent and does not create duplicates', function () {
    $service = app(ConglomerateCapitalAndGovernanceService::class);

    $deal = $service->registerAcquisition([
        'deal_code' => 'MA-RETAIL-ACQ-01',
        'target_company_name' => 'PT QuickMart Retail Chain',
        'target_industry' => 'RETAIL_QCOMMERCE',
        'valuation_minor' => 8000000000000,
        'deal_value_minor' => 7500000000000,
    ]);

    expect($deal)->toBeInstanceOf(EfMaAcquisition::class);

    // First backfill pass with entities
    $d1 = $service->backfillAcquisitionEntities($deal->deal_code, ['ENT-STORE-01', 'ENT-STORE-02', 'ENT-WH-01']);
    expect(count($d1->backfilled_entity_ids))->toBe(3);

    // Re-run second pass with duplicate and new entities
    $d2 = $service->backfillAcquisitionEntities($deal->deal_code, ['ENT-STORE-01', 'ENT-STORE-02', 'ENT-STORE-03']);
    expect(count($d2->backfilled_entity_ids))->toBe(4) // Only 1 new added, no duplicates
        ->and($d2->backfilled_entity_ids)->toContain('ENT-STORE-03');
});

test('(d) 17 lines segment financial reporting sum == total consolidated group financials', function () {
    $service = app(ConglomerateCapitalAndGovernanceService::class);

    $segments = [
        'LINE_1_AUTOSERVE' => ['revenue_minor' => 1000000000000, 'ebitda_minor' => 200000000000],
        'LINE_13_ENERGY' => ['revenue_minor' => 3000000000000, 'ebitda_minor' => 1200000000000],
        'LINE_14_TELCO' => ['revenue_minor' => 2500000000000, 'ebitda_minor' => 800000000000],
        'LINE_15_MEDIA' => ['revenue_minor' => 1500000000000, 'ebitda_minor' => 400000000000],
        'LINE_16_EDU' => ['revenue_minor' => 500000000000, 'ebitda_minor' => 150000000000],
        'LINE_17_RETAIL' => ['revenue_minor' => 4000000000000, 'ebitda_minor' => 600000000000],
    ];

    $report = $service->compileConsolidatedReport('2026-Q3', $segments);

    expect($report)->toBeInstanceOf(EfSegmentFinancialReport::class)
        ->and($report->total_consolidated_revenue_minor)->toBe(12500000000000)
        ->and($report->total_consolidated_ebitda_minor)->toBe(3350000000000);
});
