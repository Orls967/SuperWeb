<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Application\Services\UniversalCrossLineFintechService;
use Tests\TestCase;

class UniversalCrossLineFintechTest extends TestCase
{
    use RefreshDatabase;

    protected UniversalCrossLineFintechService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UniversalCrossLineFintechService::class);

        $accounts = [
            'reserve:insurance_pool:IDR' => ['liability', 'IDR'],
            'claimant:payable:CL-LOGISTICS-01:IDR' => ['liability', 'IDR'],
            'claimant:payable:CL-MINING-02:IDR' => ['liability', 'IDR'],
            'clearing:stablecoin:MINE_MOROWALI:USDC' => ['clearing', 'USDC'],
            'clearing:stablecoin:SMELTER_SINGAPORE:USDC' => ['clearing', 'USDC'],
        ];

        foreach ($accounts as $code => [$kind, $asset]) {
            LedgerAccount::firstOrCreate(
                ['code' => $code],
                [
                    'name' => "Account {$code}",
                    'kind' => $kind,
                    'asset_code' => $asset,
                    'allow_negative' => true,
                    'cached_balance' => '0',
                ]
            );
        }
    }

    public function test_universal_claims_autopilot_across_lines(): void
    {
        // 1. Logistics cold chain breach claim
        $resLogistics = $this->service->triggerUniversalClaimPayout(
            lineDomain: 'LOGISTICS',
            referenceEntityId: 'CL-LOGISTICS-01',
            policyNumber: 'POL-REEFER-001',
            claimAmountIdr: 15000000,
            reason: 'Temperature spike above 8C for 45 mins'
        );

        $this->assertEquals('disbursed', $resLogistics['status']);
        $this->assertEquals(15000000, $resLogistics['payout_amount']);

        // 2. Mining extreme weather rainfall claim
        $resMining = $this->service->triggerUniversalClaimPayout(
            lineDomain: 'MINING',
            referenceEntityId: 'CL-MINING-02',
            policyNumber: 'POL-MINE-WEATHER-99',
            claimAmountIdr: 50000000,
            reason: 'Tropical storm suspension > 24 hours'
        );

        $this->assertEquals('disbursed', $resMining['status']);
        $this->assertEquals(50000000, $resMining['payout_amount']);
    }

    public function test_stablecoin_cross_border_mining_settlement(): void
    {
        $res = $this->service->settleCrossLineStablecoin(
            sourceEntity: 'MINE_MOROWALI',
            targetEntity: 'SMELTER_SINGAPORE',
            stablecoinMinorUnits: 25000000, // 25,000 USDC
            referenceMemo: 'Nickel concentrate off-take batch B-90'
        );

        $this->assertEquals('cleared_instant', $res['status']);
        $this->assertEquals(25000000, $res['amount_usdc']);
    }
}
