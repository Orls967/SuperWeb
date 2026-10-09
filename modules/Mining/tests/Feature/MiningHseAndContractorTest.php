<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mining\Application\Services\MiningHseAndContractorService;
use Modules\Mining\Domain\Models\MiningSite;
use RuntimeException;
use Tests\TestCase;

class MiningHseAndContractorTest extends TestCase
{
    use RefreshDatabase;

    protected MiningHseAndContractorService $service;

    protected MiningSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiningHseAndContractorService::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-KALTIN-02',
            'name' => 'Kaltin Quarry & Hardrock',
            'commodity' => 'NICKEL',
            'location' => 'Central Sulawesi',
        ]);

        $accounts = [
            'min:contractor_receivable:IDR' => 'asset',
            'min:safety_penalty_revenue:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Mining {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_120_2_and_120_6_a_expired_permit_access_denied(): void
    {
        $permit = $this->service->issueWorkPermit([
            'site_id' => $this->site->id,
            'permit_number' => 'PTW-LOTO-001',
            'permit_type' => 'LOTO',
            'worker_party_id' => 'PARTY-WRK-991',
            'allowed_latitude' => -1.250000,
            'allowed_longitude' => 116.850000,
            'allowed_radius_meters' => 50.0,
            'valid_from' => Carbon::now()->subHours(5)->toDateTimeString(),
            'valid_until' => Carbon::now()->subHours(1)->toDateTimeString(), // expired
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has expired. Access revoked.');

        $this->service->validatePermitAccess($permit->id, -1.250000, 116.850000);
    }

    public function test_120_2_and_120_6_b_worker_outside_permit_area_blocked(): void
    {
        $permit = $this->service->issueWorkPermit([
            'site_id' => $this->site->id,
            'permit_number' => 'PTW-HOT-002',
            'permit_type' => 'HOT_WORK',
            'worker_party_id' => 'PARTY-WRK-992',
            'allowed_latitude' => -1.250000,
            'allowed_longitude' => 116.850000,
            'allowed_radius_meters' => 50.0,
            'valid_from' => Carbon::now()->subHour()->toDateTimeString(),
            'valid_until' => Carbon::now()->addHours(3)->toDateTimeString(),
        ]);

        // Worker is ~500m away
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Worker outside authorized permit zone');

        $this->service->validatePermitAccess($permit->id, -1.254500, 116.850000);
    }

    public function test_120_3_and_120_6_c_critical_fatigue_assignment_rejected(): void
    {
        // 13 continuous hours and 3 hours sleep -> dangerously fatigued
        $fatigueLog = $this->service->assessOperatorFatigue([
            'site_id' => $this->site->id,
            'operator_party_id' => 'OPR-EXCAVATOR-01',
            'equipment_id' => 'EQP-CAT-777',
            'work_hours_continuous' => 13.5,
            'sleep_hours_prior' => 3.0,
        ]);

        $this->assertTrue($fatigueLog->is_critical);
        $this->assertEquals('MANDATORY_REST', $fatigueLog->recommended_action);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is critically fatigued');

        $this->service->assignHeavyEquipmentOperator($fatigueLog->id);
    }

    public function test_120_1_and_120_4_anonymous_leading_indicator_reporting(): void
    {
        $incident = $this->service->reportSafetyIncident([
            'site_id' => $this->site->id,
            'incident_type' => 'NEAR_MISS',
            'is_anonymous' => true,
            'reporter_party_id' => 'EMPLOYEE-HIDDEN',
            'description' => 'Rockfall near haul road bench 14, no personnel injured.',
        ]);

        $this->assertTrue($incident->is_anonymous);
        $this->assertNull($incident->reporter_party_id);
        $this->assertEquals(15, $incident->leading_indicator_points);
    }

    public function test_120_5_and_120_6_d_e_contractor_scoring_tender_eligibility_and_ledger_penalty(): void
    {
        // Bad contractor with high incident rate
        $evaluation = $this->service->evaluateContractorSafety(
            'PARTY-CONTR-ALFA',
            35,
            4,
            50000000 // 50,000,000 IDR penalty
        );

        $this->assertEquals('BLACKLISTED', $evaluation->tier);
        $this->assertFalse($evaluation->tender_eligible);
        $this->assertNotNull($evaluation->penalty_ledger_tx_id);

        // Verify ledger balance sum = 0
        $tx = LedgerTransaction::with('entries')->findOrFail($evaluation->penalty_ledger_tx_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(2, $tx->entries->count());
    }
}
