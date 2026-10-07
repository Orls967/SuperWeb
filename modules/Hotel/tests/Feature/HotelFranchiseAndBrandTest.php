<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hotel\Application\Services\HotelFranchiseAndBrandService;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

class HotelFranchiseAndBrandTest extends TestCase
{
    use RefreshDatabase;

    protected HotelFranchiseAndBrandService $service;

    protected HotelProperty $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HotelFranchiseAndBrandService::class);

        $this->property = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-BALI-FRAN-01',
            'name' => 'Grand Horizon Luxury Resort & Spa',
            'property_type' => 'RESORT',
            'city' => 'Badung',
            'total_rooms' => 120,
        ]);

        // Register hotel ledger accounts
        $accounts = [
            'htl:franchise_receivable:IDR' => 'asset',
            'htl:franchise_fee_revenue:IDR' => 'revenue',
            'htl:royalty_revenue:IDR' => 'revenue',
            'htl:ota_penalty_receivable:IDR' => 'asset',
            'htl:parity_penalty_revenue:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Hotel {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_111_1_and_111_6_d_brand_standard_audit_determines_listing_status(): void
    {
        // 1. High score (190 / 200 = 95%) -> 5_STAR_LUXURY & ACTIVE
        $auditHigh = $this->service->conductBrandAudit($this->property, 190, 200);
        $this->assertEquals(95.0, (float) $auditHigh->compliance_score_percent);
        $this->assertEquals('5_STAR_LUXURY', $auditHigh->simulated_star_grade);
        $this->assertEquals('ACTIVE', $auditHigh->listing_status);

        // 2. Low score (100 / 200 = 50%) -> UNGRADED & SUSPENDED
        $auditLow = $this->service->conductBrandAudit($this->property, 100, 200);
        $this->assertEquals(50.0, (float) $auditLow->compliance_score_percent);
        $this->assertEquals('SUSPENDED', $auditLow->listing_status);
    }

    public function test_111_2_and_111_6_a_and_b_franchise_fee_and_monthly_royalty(): void
    {
        // 1. Onboard Franchise: initial fee 500,000,000 IDR, 5% royalty
        $contract = $this->service->onboardFranchise($this->property, 500_000_000, 5.0);
        $this->assertEquals('ACTIVE', $contract->status);

        $feeRev = LedgerAccount::where('code', 'htl:franchise_fee_revenue:IDR')->first();
        $this->assertEquals('-500000000', (string) $feeRev->cached_balance);

        // 2. Monthly Folio revenue 2,000,000,000 IDR -> 5% = 100,000,000 IDR royalty
        $royaltyIdr = $this->service->billMonthlyRoyalty($contract, 2_000_000_000);
        $this->assertEquals(100_000_000, $royaltyIdr);

        $royRev = LedgerAccount::where('code', 'htl:royalty_revenue:IDR')->first();
        $this->assertEquals('-100000000', (string) $royRev->cached_balance);
    }

    public function test_111_4_and_111_6_c_rate_parity_violation_and_penalty_accrual(): void
    {
        // Direct rate: 2,500,000 IDR. OTA undercut rate: 2,000,000 IDR. Diff: 500k -> 2x Penalty = 1,000,000 IDR
        $violation = $this->service->detectRateParityViolation(
            property: $this->property,
            otaChannel: 'AgodaGlobal',
            directBarRateIdr: 2_500_000,
            otaUndercutRateIdr: 2_000_000
        );

        $this->assertNotNull($violation);
        $this->assertEquals(1_000_000, $violation->penalty_levy_idr);
        $this->assertEquals('PENALTY_ACCRUED', $violation->status);

        $penaltyAr = LedgerAccount::where('code', 'htl:ota_penalty_receivable:IDR')->first();
        $penaltyRev = LedgerAccount::where('code', 'htl:parity_penalty_revenue:IDR')->first();

        $this->assertEquals('1000000', (string) $penaltyAr->cached_balance);
        $this->assertEquals('-1000000', (string) $penaltyRev->cached_balance);
    }
}
