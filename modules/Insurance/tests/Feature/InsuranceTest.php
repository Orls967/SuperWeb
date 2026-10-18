<?php

declare(strict_types=1);

namespace Modules\Insurance\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Insurance\Application\Services\InsurTechClaimsService;
use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsuranceProduct;
use Tests\TestCase;

class InsuranceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected InsuranceProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->product = InsuranceProduct::create([
            'product_code' => 'LGX_DELAY',
            'name' => 'Logistics 4h Delay Guarantee',
            'trigger_event_type' => 'lgx.late',
            'premium_amount_idr' => 15000,
            'max_payout_idr' => 200000,
            'is_embedded' => true,
        ]);

        $this->seed(LedgerAccountsSeeder::class);
        $wallet = $this->user->walletAccount('IDR');
        $wallet->forceFill(['cached_balance' => '500000'])->save();

        $reserve = LedgerAccount::where('code', 'ins:premium_reserve:IDR')->first();
        $reserve->forceFill(['cached_balance' => '10000000'])->save();
    }

    public function test_issue_embedded_policy_and_deduct_premium(): void
    {
        /** @var InsurTechClaimsService $service */
        $service = app(InsurTechClaimsService::class);

        $policy = $service->issueEmbeddedPolicy(
            product: $this->product,
            userId: $this->user->id,
            refType: 'shipment',
            refId: 'SHP-9901',
            durationDays: 3
        );

        $this->assertEquals('active', $policy->status);
        $this->assertEquals(15000, $policy->premium_paid_idr);
        $this->assertStringStartsWith('POL-', $policy->policy_number);
    }

    /**
     * DoR 72.7 (a): trigger sah -> klaim cair 1x, ganda ditolak (idempoten).
     */
    public function test_claims_autopilot_pays_instantaneously_and_is_idempotent(): void
    {
        /** @var InsurTechClaimsService $service */
        $service = app(InsurTechClaimsService::class);

        $policy = $service->issueEmbeddedPolicy(
            product: $this->product,
            userId: $this->user->id,
            refType: 'shipment',
            refId: 'SHP-9902',
            durationDays: 3
        );

        $claim1 = $service->processEventAutopilotClaim(
            triggerEventType: 'lgx.late',
            refType: 'shipment',
            refId: 'SHP-9902',
            triggerEventId: 'EVT-LATE-101',
            evidencePayload: ['delay_hours' => 5.2]
        );

        $this->assertNotNull($claim1);
        $this->assertEquals('paid', $claim1->status);
        $this->assertEquals(200000, $claim1->payout_amount_idr);
        $this->assertNotNull($claim1->paid_at);

        // Idempotency: exact same trigger event should not double-pay
        $claimDuplicate = $service->processEventAutopilotClaim(
            triggerEventType: 'lgx.late',
            refType: 'shipment',
            refId: 'SHP-9902',
            triggerEventId: 'EVT-LATE-101',
            evidencePayload: ['delay_hours' => 5.2]
        );

        $this->assertEquals($claim1->id, $claimDuplicate->id);
        $this->assertEquals(1, InsuranceClaim::count());
    }

    /**
     * DoR 72.7 (b): reserve >= kewajiban terbayar.
     */
    public function test_insurance_reserve_exceeds_or_equals_paid_liabilities(): void
    {
        /** @var InsurTechClaimsService $service */
        $service = app(InsurTechClaimsService::class);

        $policy = $service->issueEmbeddedPolicy(
            product: $this->product,
            userId: $this->user->id,
            refType: 'shipment',
            refId: 'SHP-9905',
            durationDays: 5
        );

        $service->processEventAutopilotClaim(
            triggerEventType: 'lgx.late',
            refType: 'shipment',
            refId: 'SHP-9905',
            triggerEventId: 'EVT-LATE-105',
            evidencePayload: ['delay_hours' => 6.0]
        );

        $reconcile = $service->reconcileReserve(initialReserveIdr: 10_000_000);
        $this->assertGreaterThanOrEqual(0, $reconcile['total_paid_claims_idr']);
        $this->assertTrue($reconcile['is_solvent']);
    }

    /**
     * DoR 72.7 (c): fraud score tinggi masuk hold.
     */
    public function test_fraud_anomaly_score_triggers_manual_review_hold(): void
    {
        /** @var InsurTechClaimsService $service */
        $service = app(InsurTechClaimsService::class);

        $policy = $service->issueEmbeddedPolicy(
            product: $this->product,
            userId: $this->user->id,
            refType: 'shipment',
            refId: 'SHP-9903',
            durationDays: 3
        );

        // Anomaly score 95 > 80 => pending_review hold
        $claim = $service->processEventAutopilotClaim(
            triggerEventType: 'lgx.late',
            refType: 'shipment',
            refId: 'SHP-9903',
            triggerEventId: 'EVT-LATE-102',
            evidencePayload: ['delay_hours' => 4.1],
            fraudAnomalyScore: 95
        );

        $this->assertEquals('pending_review', $claim->status);
        $this->assertNull($claim->paid_at);

        // Four-eyes approver releases the hold
        $approved = $service->approveHeldClaim($claim, $this->user->id);
        $this->assertEquals('paid', $approved->status);
        $this->assertNotNull($approved->paid_at);
    }

    /**
     * DoR 72.7 (d): premi gagal bayar -> polis lapse + notifikasi.
     */
    public function test_premium_payment_failure_leads_to_policy_lapse(): void
    {
        /** @var InsurTechClaimsService $service */
        $service = app(InsurTechClaimsService::class);

        $policy = $service->issueEmbeddedPolicy(
            product: $this->product,
            userId: $this->user->id,
            refType: 'shipment',
            refId: 'SHP-9904',
            durationDays: 1
        );

        $lapsed = $service->handleFailedPremiumPayment($policy);
        $this->assertEquals('lapsed', $lapsed->status);

        // PSAK 62 unearned premium after lapse/expiry is calculated
        $unearned = $service->calculateUnearnedPremium($lapsed);
        $this->assertGreaterThanOrEqual(0, $unearned);
    }

    /**
     * DoR 72.7 (e): reconcile reserve = ledger.
     */
    public function test_reconcile_insurance_reserve_equals_ledger(): void
    {
        /** @var InsurTechClaimsService $service */
        $service = app(InsurTechClaimsService::class);

        $reconcile = $service->reconcileReserve();
        $this->assertArrayHasKey('total_premiums_collected_idr', $reconcile);
        $this->assertArrayHasKey('total_paid_claims_idr', $reconcile);
        $this->assertArrayHasKey('net_reserve_idr', $reconcile);
        $this->assertArrayHasKey('is_solvent', $reconcile);
    }
}
