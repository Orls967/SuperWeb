<?php

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hospital\Application\Services\MedicalTourismAndMembershipService;
use Modules\Hospital\Domain\Models\Patient;
use RuntimeException;
use Tests\TestCase;

class MedicalTourismAndMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected MedicalTourismAndMembershipService $service;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MedicalTourismAndMembershipService::class);

        $this->patient = Patient::create([
            'mrn' => 'MRN-TOUR-01',
            'name' => 'International Medical Tourist',
            'date_of_birth' => '1979-05-18',
            'blood_type' => 'O+',
            'passport_hash' => hash('sha256', 'PASSPORT-TOUR-01'),
        ]);

        // Register ledger accounts
        $accounts = [
            'hsp:tourism_escrow_deposit:IDR' => 'asset',
            'hsp:tourism_unearned_escrow:IDR' => 'liability',
            'hsp:hospital_revenue:IDR' => 'revenue',
            'htl:hotel_vendor_payable:IDR' => 'liability',
            'lgx:transport_vendor_payable:IDR' => 'liability',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Hospital {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_108_1_and_108_6_a_and_b_medical_tourism_milestones_and_multi_vendor_settlement(): void
    {
        // 1. Book package: Hospital (30m) + Hotel (15m) + Transport (5m) = 50,000,000 IDR Total
        $pkg = $this->service->bookTourismPackage(
            patient: $this->patient,
            packageName: 'Bali VIP Health & Spine Screening',
            hospitalShareIdr: 30_000_000,
            hotelShareIdr: 15_000_000,
            transportShareIdr: 5_000_000
        );

        $this->assertEquals(50_000_000, $pkg->total_price_idr);
        $this->assertEquals('BOOKED', $pkg->current_milestone);
        $this->assertEquals('ESCROWED', $pkg->status);

        // Verify initial deposit escrow hold in ledger
        $dep = LedgerAccount::where('code', 'hsp:tourism_escrow_deposit:IDR')->first();
        $this->assertEquals('50000000', (string) $dep->cached_balance);

        // 2. Rule 108.6 (b): Milestone skip attempt must fail
        try {
            $this->service->advanceMilestone($pkg, 'SETTLED');
            $this->fail('Expected milestone transition error');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Invalid milestone transition', $e->getMessage());
        }

        // 3. Advance sequentially
        $pkg = $this->service->advanceMilestone($pkg, 'CHECKED_IN_HOSPITAL');
        $this->assertEquals('CHECKED_IN_HOSPITAL', $pkg->current_milestone);

        $pkg = $this->service->advanceMilestone($pkg, 'PROCEDURE_COMPLETED');
        $this->assertEquals('PROCEDURE_COMPLETED', $pkg->current_milestone);

        $pkg = $this->service->advanceMilestone($pkg, 'SETTLED');
        $this->assertEquals('SETTLED', $pkg->current_milestone);
        $this->assertEquals('COMPLETED', $pkg->status);

        // 4. Verify Rule 108.6 (a): Multi-vendor settlement balances perfectly
        $hospRev = LedgerAccount::where('code', 'hsp:hospital_revenue:IDR')->first();
        $htlPayable = LedgerAccount::where('code', 'htl:hotel_vendor_payable:IDR')->first();
        $lgxPayable = LedgerAccount::where('code', 'lgx:transport_vendor_payable:IDR')->first();

        $this->assertEquals('-30000000', (string) $hospRev->cached_balance);
        $this->assertEquals('-15000000', (string) $htlPayable->cached_balance);
        $this->assertEquals('-5000000', (string) $lgxPayable->cached_balance);
    }

    public function test_108_3_and_108_6_c_membership_quota_limit(): void
    {
        $mem = $this->service->createMembership($this->patient, 'PLATINUM', 500);

        // Redeem 300 pts
        $mem = $this->service->redeemMembershipCredits($mem, 300);
        $this->assertEquals(200, $mem->remaining_credits_pts);

        // Attempting to redeem 250 pts (more than 200) must fail
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceed remaining membership wellness credits quota');

        $this->service->redeemMembershipCredits($mem, 250);
    }

    public function test_108_4_wearable_adherence_records_and_rewards(): void
    {
        $adherence = $this->service->recordWearableAdherence(
            patient: $this->patient,
            date: '2026-10-07',
            steps: 12000,
            sleepHours: 8
        );

        $this->assertGreaterThanOrEqual(0.8, $adherence->adherence_score);
        $this->assertEquals(50, $adherence->pts_rewarded);
        $this->assertNotEmpty($adherence->proof_hash);
    }
}
