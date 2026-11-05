<?php

declare(strict_types=1);

namespace Tests\Acceptance\InsReinsurance;

use App\Models\User;
use App\Quality\Audit\Fixtures\ReinsAuditCorruptionFixture;
use Carbon\CarbonImmutable;
use Database\Seeders\InsuranceT1Seeder;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\Ledger;
use Modules\Insurance\Application\Actions\CedeClaimAction;
use Modules\Insurance\Application\Actions\CedePolicyPremiumAction;
use Modules\Insurance\Application\Actions\RecordCessionAction;
use Modules\Insurance\Application\Services\CatBondService;
use Modules\Insurance\Application\Services\CessionEngine;
use Modules\Insurance\Application\Services\SolvencyService;
use Modules\Insurance\Domain\DTOs\CessionLineData;
use Modules\Insurance\Domain\DTOs\CessionPlan;
use Modules\Insurance\Domain\Enums\CatBondStatus;
use Modules\Insurance\Domain\Enums\CessionKind;
use Modules\Insurance\Domain\Enums\SolvencyActionType;
use Modules\Insurance\Domain\Enums\SolvencyAlertLevel;
use Modules\Insurance\Domain\Enums\TreatyStatus;
use Modules\Insurance\Domain\Enums\TreatyType;
use Modules\Insurance\Domain\Models\CatBond;
use Modules\Insurance\Domain\Models\CessionEvent;
use Modules\Insurance\Domain\Models\InsuranceClaimPenuh;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;
use Modules\Insurance\Domain\Models\InsuranceProductPenuh;
use Modules\Insurance\Domain\Models\ReinsuranceTreaty;
use Modules\Insurance\Domain\Models\ReinsuranceTreatyPanel;
use Modules\Party\Domain\Models\Party;
use Modules\Rwa\Contracts\VotingWeightReader;
use Tests\TestCase;

final class InsReinsuranceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(InsuranceT1Seeder::class);
    }

    public function test_1_akses_kontrol_rute_reasuransi_kapital_cat_dan_investor(): void
    {
        $reinsMgr = User::factory()->create(['role' => 'reinsurance_mgr']);
        $actuary = User::factory()->create(['role' => 'actuary']);
        $investor = User::factory()->create(['role' => 'investor']);
        $unauthorized = User::factory()->create(['role' => 'farmer']);

        // 1. Reinsurance Manager routes
        $this->actingAs($unauthorized)->get(route('insurance.reinsurance.treaties.index'))->assertForbidden();
        $this->actingAs($reinsMgr)->get(route('insurance.reinsurance.treaties.index'))->assertOk();
        $this->actingAs($reinsMgr)->get(route('insurance.reinsurance.bordereaux.index'))->assertOk();
        $this->actingAs($reinsMgr)->get(route('insurance.reinsurance.account-current.index'))->assertOk();
        $this->actingAs($reinsMgr)->get(route('insurance.cat-bonds.index'))->assertOk();

        // 2. Actuary routes
        $this->actingAs($unauthorized)->get(route('insurance.capital.index'))->assertForbidden();
        $this->actingAs($actuary)->get(route('insurance.capital.index'))->assertOk();
        $this->actingAs($actuary)->get(route('insurance.cat.index'))->assertOk();

        // 3. Investor routes
        $this->actingAs($unauthorized)->get(route('investor.cat-bonds.index'))->assertForbidden();
        $this->actingAs($investor)->get(route('investor.cat-bonds.index'))->assertOk();
    }

    public function test_2_alur_utama_cession_vektor_a2_posting_ledger_dan_idempotensi(): void
    {
        $policy = InsurancePolicyPenuh::firstOrFail();
        $reinsurer = Party::firstOrFail();

        // Siapkan treaty dan layer sesuai Vektor A2
        $treaty = ReinsuranceTreaty::create([
            'treaty_code' => 'TRT-A2-TEST',
            'name' => 'A2 Test Program Treaty',
            'type' => TreatyType::QuotaShare,
            'line_category' => $policy->product->line_category,
            'status' => TreatyStatus::Active,
            'inception_date' => '2026-01-01',
            'expiry_date' => '2026-12-31',
            'quota_share_bps' => 3000,
            'commission_bps' => 1000,
        ]);
        ReinsuranceTreatyPanel::create([
            'treaty_id' => $treaty->id,
            'reinsurer_party_id' => $reinsurer->id,
            'share_bps' => 10000,
        ]);

        $recordAction = app(RecordCessionAction::class);

        // Vektor A2: Gross 1.500.000.000; QS 30% -> 450M; L1 400M xs 300M -> 400M; L2 500M xs 700M -> 350M; Retained 300M.
        $plan = new CessionPlan(
            grossIdr: 1500000000,
            retainedIdr: 300000000,
            lines: [
                new CessionLineData(
                    treatyId: $treaty->id,
                    reinsurerPartyId: (string) $reinsurer->id,
                    layerCode: 'QS',
                    cededIdr: 450000000,
                    commissionIdr: 45000000,
                    reinstatementIdr: 0
                ),
                new CessionLineData(
                    treatyId: $treaty->id,
                    reinsurerPartyId: (string) $reinsurer->id,
                    layerCode: 'L1',
                    cededIdr: 400000000,
                    commissionIdr: 0,
                    reinstatementIdr: 0
                ),
                new CessionLineData(
                    treatyId: $treaty->id,
                    reinsurerPartyId: (string) $reinsurer->id,
                    layerCode: 'L2',
                    cededIdr: 350000000,
                    commissionIdr: 0,
                    reinstatementIdr: 35000000
                ),
            ],
            snapshot: ['vector' => 'A2']
        );

        $eventKey = 'a2:test:cession:001';
        $event = $recordAction->execute(
            eventKey: $eventKey,
            kind: CessionKind::Claim,
            plan: $plan,
            at: CarbonImmutable::now(),
            policyId: $policy->id
        );

        // Assert nilai persis
        $this->assertSame(1500000000, $event->gross_idr);
        $this->assertSame(1200000000, $event->ceded_idr); // 450M + 400M + 350M
        $this->assertSame(300000000, $event->retained_idr);
        $this->assertSame(35000000, $event->reinstatement_idr);
        $this->assertNotNull($event->ledger_transaction_id);

        // Idempotensi: event_key sama menghasilkan event yang sama tanpa double posting
        $event2 = $recordAction->execute(
            eventKey: $eventKey,
            kind: CessionKind::Claim,
            plan: $plan,
            at: CarbonImmutable::now(),
            policyId: $policy->id
        );
        $this->assertSame($event->id, $event2->id);

        // Validasi bordereau & account current
        $this->artisan('ins:cede-period', ['period' => date('Y-m')])->assertSuccessful();
    }

    public function test_3_solvabilitas_rbc_vektor_a3_peringatan_dan_rekomendasi_what_if(): void
    {
        $service = app(SolvencyService::class);

        // Vektor A3:
        // R = premi 300.000.000, cadangan 400.000.000, pasar 200.000.000
        // operational 30.000.000, modal 1.200.000.000
        // -> RBC 722.820.323, rasio 16601 bps
        $customCharges = [
            'premium' => 300000000,
            'reserve' => 400000000,
            'market' => 200000000,
        ];

        $snapshotHealthy = $service->createSnapshot(
            valuationDate: CarbonImmutable::parse('2026-11-01'),
            customRiskCharges: $customCharges,
            customCapital: 1200000000
        );

        $this->assertSame(722820323, $snapshotHealthy->rbc_idr);
        $this->assertSame(16601, $snapshotHealthy->ratio_bps);
        $this->assertSame(SolvencyAlertLevel::Healthy, $snapshotHealthy->alert_level);

        // Kondisi modal tertekan (< 120% = 12000 bps) -> Alert Regulator
        $snapshotStressed = $service->createSnapshot(
            valuationDate: CarbonImmutable::parse('2026-11-02'),
            customRiskCharges: $customCharges,
            customCapital: 800000000 // 800jt / 722jt = 11067 bps (< 12000 bps)
        );

        $this->assertSame(SolvencyAlertLevel::Regulator, $snapshotStressed->alert_level);
        $this->assertNotEmpty($snapshotStressed->actions);

        // Simulasi what-if injeksi modal
        $simRatio = $service->simulateAction(
            snapshot: $snapshotStressed,
            type: SolvencyActionType::CapitalInjection,
            params: ['amount_idr' => 400000000] // +400jt modal -> 1.200jt -> rasio kembali 16601 bps
        );
        $this->assertSame(16601, $simRatio);
    }

    public function test_4_idor_portal_investor_cat_bond(): void
    {
        $userA = User::factory()->create(['role' => 'investor']);
        $userB = User::factory()->create(['role' => 'investor']);

        $bond = CatBond::firstOrFail();

        // Mock holder token RWA agar User A memiliki unit dan User B 0 unit
        $this->app->bind(VotingWeightReader::class, function () use ($userA) {
            return new class($userA->id) implements VotingWeightReader {
                public function __construct(private readonly int $userId) {}

                public function tokenUnitsByHolderAt(string $assetCode, CarbonImmutable $at): array
                {
                    return [$this->userId => 500];
                }
            };
        });

        // User A berhak melihat posisinya (200 OK)
        $this->actingAs($userA)->get(route('investor.cat-bonds.show', $bond->id))
            ->assertOk();

        // User B tidak memiliki unit pada Cat Bond ini (403 Forbidden)
        $this->actingAs($userB)->get(route('investor.cat-bonds.show', $bond->id))
            ->assertForbidden();
    }

    public function test_5_audit_dua_sumber_reinsurance_bersih_exit_0_dan_fixture_exit_nonzero(): void
    {
        // 1. Audit bersih
        $this->artisan('ins:reinsurance-audit')->assertSuccessful();

        // 2. Korupsi lewat fixture
        $fixture = new ReinsAuditCorruptionFixture;
        $keyword = $fixture->corrupt();

        // 3. Audit mendeteksi kegagalan dan exit non-zero
        $this->artisan('ins:reinsurance-audit')
            ->assertFailed()
            ->expectsOutputToContain($keyword);
    }
}
