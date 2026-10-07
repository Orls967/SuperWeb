<?php

declare(strict_types=1);

namespace Modules\Insurance\tests\Feature;

use App\Models\User;
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

        LedgerAccount::create([
            'code' => "wallet:user:{$this->user->id}:IDR",
            'name' => "User {$this->user->id} Wallet",
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '500000',
        ]);

        LedgerAccount::create([
            'code' => 'ins:premium_reserve:IDR',
            'name' => 'Insurance Reserve Pool',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '10000000',
        ]);
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
    }

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
    }
}
