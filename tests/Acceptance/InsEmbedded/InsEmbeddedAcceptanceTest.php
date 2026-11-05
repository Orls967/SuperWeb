<?php

declare(strict_types=1);

namespace Tests\Acceptance\InsEmbedded;

use App\Models\User;
use App\Quality\Audit\Fixtures\EmbeddedAuditCorruptionFixture;
use Carbon\CarbonImmutable;
use Database\Seeders\InsuranceT1Seeder;
use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Agri\Contracts\NdviIndexReader;
use Modules\Insurance\Application\Actions\AssessFraudRiskAction;
use Modules\Insurance\Application\Actions\DecideSiuCaseAction;
use Modules\Insurance\Application\Actions\ExecuteParametricTriggerAction;
use Modules\Insurance\Application\Actions\IssueEmbeddedPolicyAction;
use Modules\Insurance\Application\Actions\RegisterBrokerAction;
use Modules\Insurance\Application\Actions\SettleBrokerCommissionAction;
use Modules\Insurance\Domain\Models\BlacklistedEntity;
use Modules\Insurance\Domain\Models\EmbedProduct;
use Modules\Insurance\Domain\Models\FraudScore;
use Modules\Insurance\Domain\Models\MarketBroker;
use Modules\Insurance\Domain\Models\MarketCommission;
use Modules\Insurance\Domain\Models\ParametricTrigger;
use Modules\Insurance\Domain\Models\SiuInvestigation;
use Modules\Party\Contracts\Party;
use Tests\TestCase;

final class InsEmbeddedAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(InsuranceT1Seeder::class);
        $this->seedEmbeddedData();
    }

    private function seedEmbeddedData(): void
    {
        // 1. Produk embedded per lini
        EmbedProduct::firstOrCreate(
            ['code' => 'EMB-HOTEL-CANCEL'],
            [
                'line_domain' => 'HTL',
                'name' => 'Hotel Booking Cancellation Protection',
                'coverage_type' => 'CANCELLATION',
                'default_sum_insured_idr' => 2000000,
                'premium_rate_bps' => 300, // 3%
                'min_premium_idr' => 25000,
                'is_active' => true,
            ]
        );

        EmbedProduct::firstOrCreate(
            ['code' => 'EMB-AGRI-DROUGHT'],
            [
                'line_domain' => 'AGRI',
                'name' => 'Agri Weather & NDVI Drought Protection',
                'coverage_type' => 'PARAMETRIC',
                'default_sum_insured_idr' => 10000000,
                'premium_rate_bps' => 250, // 2.5%
                'min_premium_idr' => 50000,
                'is_active' => true,
            ]
        );

        // 2. Trigger parametrik
        ParametricTrigger::firstOrCreate(
            ['trigger_code' => 'TRIG-AGRI-DROUGHT-01'],
            [
                'metric_name' => 'RAINFALL_DEFICIT',
                'region_or_plot' => 'PLOT-AGRI-JBR-001',
                'threshold_value' => 5000, // defisit > 50%
                'comparison_operator' => '>',
                'payout_percentage_bps' => 8000, // 80% santunan
                'is_active' => true,
            ]
        );
    }

    public function test_1_akses_kontrol_rute_embedded_broker_hub_dan_siu(): void
    {
        $brokerUser = User::factory()->create(['role' => 'broker']);
        $underwriter = User::factory()->create(['role' => 'underwriter']);
        $adjuster = User::factory()->create(['role' => 'claims_adjuster']);
        $customer = User::factory()->create(['role' => 'customer']);
        $unauthorized = User::factory()->create(['role' => 'farmer']);

        // 1. Embedded Routes
        $this->actingAs($unauthorized)->get(route('insurance.embedded.matrix'))->assertForbidden();
        $this->actingAs($brokerUser)->get(route('insurance.embedded.matrix'))->assertOk();
        $this->actingAs($brokerUser)->get(route('insurance.embedded.policies'))->assertOk();

        // 2. Broker Marketplace
        $this->actingAs($unauthorized)->get(route('insurance.brokers.index'))->assertForbidden();
        $this->actingAs($brokerUser)->get(route('insurance.brokers.index'))->assertOk();
        $this->actingAs($brokerUser)->get(route('insurance.brokers.commissions'))->assertOk();

        // 3. Customer Hub
        $this->actingAs($unauthorized)->get(route('insurance.hub.dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('insurance.hub.dashboard'))->assertOk();

        // 4. SIU Routes
        $this->actingAs($unauthorized)->get(route('insurance.siu.index'))->assertForbidden();
        $this->actingAs($adjuster)->get(route('insurance.siu.index'))->assertOk();
    }

    public function test_2_alur_utama_embedded_policy_issuance_broker_commission_dan_ledger(): void
    {
        $customerParty = Party::firstOrFail();
        $brokerParty = Party::skip(1)->firstOrFail();

        // 1. Daftarkan broker di marketplace
        $registerAction = app(RegisterBrokerAction::class);
        $broker = $registerAction->execute(
            partyId: (string) $brokerParty->id,
            licenseNumber: 'BRK-LIC-2026-999',
            tier: 'gold',
            commissionRateBps: 1500 // 15%
        );

        $this->assertInstanceOf(MarketBroker::class, $broker);
        $this->assertSame('gold', $broker->tier);

        // 2. Terbitkan polis embedded untuk booking hotel
        $issueAction = app(IssueEmbeddedPolicyAction::class);
        $policy = $issueAction->execute(
            eventKey: 'acc:test:emb:issue:001',
            productCode: 'EMB-HOTEL-CANCEL',
            customerPartyId: (string) $customerParty->id,
            contextType: 'booking',
            contextRef: 'HTL-RES-2026-0899',
            customSumInsuredIdr: 5000000,
            brokerPartyId: (string) $brokerParty->id,
        );

        $this->assertSame(5000000, $policy->sum_insured_idr);
        $this->assertSame(150000, $policy->premium_idr); // 3% dari 5jt = 150rb
        $this->assertSame(22500, $policy->broker_commission_idr); // 15% dari 150rb = 22.500
        $this->assertNotNull($policy->ledger_transaction_id);

        // 3. Settle komisi broker
        $commission = MarketCommission::where('embedded_policy_id', $policy->id)->firstOrFail();
        $this->assertSame('pending', $commission->status);

        $settleAction = app(SettleBrokerCommissionAction::class);
        $settled = $settleAction->execute($commission->id);

        $this->assertSame('settled', $settled->status);
        $this->assertNotNull($settled->ledger_transaction_id);
    }

    public function test_3_parametric_trigger_otomatis_vektor_ndvi_agri_dan_payout(): void
    {
        $customerParty = Party::firstOrFail();

        // 1. Terbitkan polis asuransi parametrik tani
        $issueAction = app(IssueEmbeddedPolicyAction::class);
        $policy = $issueAction->execute(
            eventKey: 'acc:test:agri:param:001',
            productCode: 'EMB-AGRI-DROUGHT',
            customerPartyId: (string) $customerParty->id,
            contextType: 'parcel',
            contextRef: 'PLOT-AGRI-JBR-001',
            customSumInsuredIdr: 10000000,
        );

        $this->assertSame('active', $policy->status);

        // 2. Baca data indeks dari Kontrak Antar-Modul G7-K1 NdviIndexReader
        $ndviReader = app(NdviIndexReader::class);
        $indexData = $ndviReader->readIndex('PLOT-AGRI-JBR-001', CarbonImmutable::now());

        $this->assertArrayHasKey('ndvi_bps', $indexData);
        $this->assertArrayHasKey('rainfall_deficit_bps', $indexData);

        // 3. Eksekusi trigger parametrik saat defisit hujan melampaui ambang (> 5000 bps)
        $executeAction = app(ExecuteParametricTriggerAction::class);
        $event = $executeAction->execute(
            eventKey: 'acc:test:agri:trig:ev:001',
            triggerCode: 'TRIG-AGRI-DROUGHT-01',
            observedValue: 6500, // defisit 65% > 50%
            oraclePayload: $indexData,
            oracleSignature: 'sig-sentinel-verified-2026',
        );

        $this->assertSame(1, $event->policies_paid);
        $this->assertSame(8000000, $event->total_payout_idr); // 80% dari 10jt = 8jt

        // Status polis terupdate menjadi claimed
        $policy->refresh();
        $this->assertSame('claimed', $policy->status);
    }

    public function test_4_fraud_detection_scoring_a6_union_find_dan_siu_hold(): void
    {
        $subjectParty = Party::firstOrFail();
        $assessAction = app(AssessFraudRiskAction::class);

        // Vektor Sindikat A6 (UnionFind): Jaringan 4 entitas terhubung dalam 1 ring
        $entities = ['CLAIM-A', 'CLAIM-B', 'CLAIM-C', 'CLAIM-D'];
        $edges = [
            ['CLAIM-A', 'CLAIM-B'], // no rekening sama
            ['CLAIM-B', 'CLAIM-C'], // no HP sama
            ['CLAIM-C', 'CLAIM-D'], // kontak darurat sama
        ];

        $metrics = [
            'frequency_last_90d' => 4, // > 3 klaim dalam 90 hari
            'telematics_contradiction' => true,
        ];

        $assessment = $assessAction->execute(
            assessmentKey: 'acc:test:fraud:eval:001',
            targetType: 'claim',
            targetRef: 'CLM-TEST-RING-001',
            subjectPartyId: (string) $subjectParty->id,
            networkEntities: $entities,
            networkEdges: $edges,
            claimMetrics: $metrics,
        );

        // Assert skor risiko dan ring fraud
        $this->assertTrue($assessment->ring_fraud_detected);
        $this->assertGreaterThanOrEqual(80, $assessment->risk_score);
        $this->assertSame('critical', $assessment->risk_level);
        $this->assertSame('siu_investigation', $assessment->recommended_action);

        // Verifikasi berkas investigasi SIU otomatis dibuat
        $siuCase = SiuInvestigation::where('fraud_assessment_id', $assessment->id)->first();
        $this->assertNotNull($siuCase);
        $this->assertSame('assigned', $siuCase->status);
    }

    public function test_5_four_eyes_siu_putusan_blacklist_dan_audit_dua_sumber(): void
    {
        $investigator = User::factory()->create(['role' => 'claims_adjuster']);
        $decider = User::factory()->create(['role' => 'underwriter']);
        $subjectParty = Party::firstOrFail();

        $assessment = FraudScore::create([
            'assessment_key' => 'acc:test:fraud:5:key',
            'target_type' => 'claim',
            'target_ref' => 'CLM-TEST-5-001',
            'risk_score' => 85,
            'risk_level' => 'critical',
            'ring_fraud_detected' => true,
            'ring_cluster_nodes' => [['A', 'B', 'C']],
            'score_breakdown' => ['ring' => 85],
            'recommended_action' => 'siu_investigation',
        ]);

        $siuCase = SiuInvestigation::create([
            'case_number' => 'SIU-TEST-999',
            'fraud_assessment_id' => $assessment->id,
            'subject_party_id' => $subjectParty->id,
            'reason' => 'Sindikat pemalsuan kuitansi bengkel',
            'status' => 'investigating',
            'assigned_to' => $investigator->id,
        ]);

        $decideAction = app(DecideSiuCaseAction::class);

        // 1. Uji pelanggaran Four-Eyes: Penyelidik tidak boleh memutus sendiri
        $caughtFourEyes = false;
        try {
            $decideAction->execute(
                investigationId: $siuCase->id,
                decision: 'confirmed_fraud',
                decisionNotes: 'Memutus sendiri secara sepihak',
                decidedByUserId: $investigator->id, // Penyelidik sama dengan pemutus
            );
        } catch (DomainException $e) {
            $caughtFourEyes = true;
            $this->assertStringContainsString('Four-Eyes', $e->getMessage());
        }
        $this->assertTrue($caughtFourEyes);

        // 2. Putusan sah oleh pihak independen (Decider)
        $decidedCase = $decideAction->execute(
            investigationId: $siuCase->id,
            decision: 'confirmed_fraud',
            decisionNotes: 'Bukti forensik telematika dan kuitansi fiktif terkonfirmasi',
            decidedByUserId: $decider->id,
            identityNumberToBlacklist: 'NIK-3201-9988-7766',
        );

        $this->assertSame('confirmed_fraud', $decidedCase->status);

        // Verifikasi entitas diblacklist
        $blacklist = BlacklistedEntity::where('source_case_number', 'SIU-TEST-999')->first();
        $this->assertNotNull($blacklist);
        $this->assertSame('NIK-3201-9988-7766', $blacklist->identity_number);

        // 3. Uji Audit Dua-Sumber Bersih & Fixture
        $exitClean = Artisan::call('ins:embedded-audit');
        $this->assertSame(0, $exitClean);

        $fixture = new EmbeddedAuditCorruptionFixture;
        $fixture->corrupt();

        $exitCorrupted = Artisan::call('ins:embedded-audit');
        $this->assertSame(1, $exitCorrupted);
    }
}
