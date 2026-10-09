<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Contract\Application\Services\ContractAmendmentService;
use Modules\Contract\Application\Services\ContractFinanceService;
use Modules\Contract\Application\Services\ContractRateResolver;
use Modules\Contract\Application\Services\ContractReportService;
use Modules\Contract\Application\Services\ContractRiskService;
use Modules\Contract\Application\Services\ContractService;
use Modules\Contract\Application\Services\ContractUsageService;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Enums\ContractType;
use Modules\Contract\Domain\Enums\MilestoneStatus;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractAttachment;
use Modules\Contract\Domain\Models\ContractMilestone;
use Modules\Contract\Domain\Models\EscalationIndex;
use Modules\Contract\Domain\Models\PenaltyRule;
use Modules\Core\Contracts\DocumentStoreInterface;
use Modules\Core\Domain\Models\OutboxMessage;
use Modules\Core\Domain\Models\PlatformNotification;
use Modules\Logistics\Application\Actions\QuoteShipmentAction;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\RateBracket;
use Modules\Logistics\Domain\Models\RateCard;
use Modules\Logistics\Domain\Services\ChargeableWeightCalculator;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;
use Tests\TestCase;

/**
 * Regresi Fase 28.6–28.8: lampiran (DocumentStore), dashboard obligasi,
 * dan pengingat `ctr:remind` (in-app + outbox idempoten).
 */
class ContractObligationsTest extends TestCase
{
    use RefreshDatabase;

    private ContractService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
        $this->service = app(ContractService::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function makeContract(array $extra = []): Contract
    {
        $le = LegalEntity::create([
            'name' => 'Obligasi Corp',
            'short_name' => 'OBLI',
            'entity_type' => 'company',
            'functional_currency' => 'IDR',
            'fiscal_year_start' => '01-01',
            'is_active' => true,
        ]);

        $mk = function (string $name): Party {
            return Party::create([
                'type' => 'company',
                'name' => $name,
                'name_normalized' => strtolower($name),
                'status' => 'verified',
                'is_active' => true,
            ]);
        };

        $p1 = $mk('Pihak Alpha');
        $p2 = $mk('Pihak Beta');

        return $this->service->createContract(array_merge([
            'legal_entity_id' => $le->id,
            'title' => 'Kontrak Obligasi',
            'contract_type' => ContractType::Service->value,
            'total_value_idr' => 10_000_000,
            'created_by' => $this->admin->id,
            'created_by_name' => 'Admin',
            'parties' => [
                ['party_id' => $p1->id, 'role' => 'first_party', 'signing_order' => 1],
                ['party_id' => $p2->id, 'role' => 'second_party', 'signing_order' => 2],
            ],
        ], $extra));
    }

    // ── 28.6 Lampiran ──────────────────────────────────────────────────────

    public function test_contract_attachment_stores_document_and_links_contract(): void
    {
        Storage::fake('local');
        $contract = $this->makeContract();

        $file = UploadedFile::fake()->create('annex-a.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(
            route('contract.attachment.store', $contract),
            ['file' => $file, 'label' => 'Annex A - SLA', 'kind' => 'annex']
        );

        $response->assertSessionHas('success');
        $this->assertSame(1, ContractAttachment::where('contract_id', $contract->id)->count());

        $attachment = ContractAttachment::where('contract_id', $contract->id)->firstOrFail();
        $this->assertSame('annex', $attachment->kind);
        $this->assertSame($contract->legal_entity_id, $attachment->legal_entity_id);
        $this->assertNotNull($attachment->document_id);
        $this->assertSame(64, strlen((string) $attachment->document->checksum_sha256));
        $this->assertTrue(app(DocumentStoreInterface::class)->verifyChecksum($attachment->document_id));
    }

    public function test_attachment_rejected_when_contract_is_locked(): void
    {
        Storage::fake('local');
        $contract = $this->makeContract();
        $contract->update(['status' => ContractStatus::Active->value]);

        $file = UploadedFile::fake()->create('annex.pdf', 10, 'application/pdf');

        $this->actingAs($this->admin)
            ->post(route('contract.attachment.store', $contract), [
                'file' => $file, 'label' => 'Terlambat', 'kind' => 'annex',
            ])
            ->assertStatus(403);
    }

    public function test_attachment_rejects_dangerous_extension(): void
    {
        Storage::fake('local');
        $contract = $this->makeContract();

        $file = UploadedFile::fake()->create('payload.php', 10, 'text/x-php');

        $this->actingAs($this->admin)
            ->post(route('contract.attachment.store', $contract), [
                'file' => $file, 'label' => 'Bahaya', 'kind' => 'supporting',
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_attachment_can_be_removed_from_draft(): void
    {
        Storage::fake('local');
        $contract = $this->makeContract();

        $file = UploadedFile::fake()->create('draft.pdf', 10, 'application/pdf');
        $this->actingAs($this->admin)->post(route('contract.attachment.store', $contract), [
            'file' => $file, 'label' => 'Draft', 'kind' => 'supporting',
        ]);

        $attachment = ContractAttachment::where('contract_id', $contract->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('contract.attachment.destroy', [$contract, $attachment]))
            ->assertSessionHas('success');

        $this->assertSame(0, ContractAttachment::where('contract_id', $contract->id)->count());
    }

    // ── 28.7 Dashboard obligasi ────────────────────────────────────────────

    public function test_obligation_dashboard_lists_due_milestones_and_expiring_contracts(): void
    {
        $contract = $this->makeContract([
            'end_date' => now()->addDays(10)->toDateString(),
            'notice_period_days' => 5,
        ]);
        $contract->update(['status' => ContractStatus::Active->value, 'activated_at' => now()]);

        ContractMilestone::create([
            'contract_id' => $contract->id,
            'title' => 'Deliverable mendekat',
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => MilestoneStatus::Pending->value,
            'amount_idr' => 1_000_000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('contract.obligations', ['days' => 30]));

        $response->assertOk();
        $response->assertSee('Deliverable mendekat');
        $response->assertSee($contract->contract_number);
        // notice_period 5 hari dari end_date 10 hari lagi => notice date 5 hari lagi <= horizon 30
        $response->assertSee('Notice period telah dimulai');
    }

    public function test_obligation_dashboard_forbidden_for_customer(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get(route('contract.obligations'))->assertForbidden();
    }

    // ── 28.8 Pengingat ctr:remind ─────────────────────────────────────────

    public function test_remind_command_notifies_in_app_and_records_outbox_idempotently(): void
    {
        $contract = $this->makeContract([
            'end_date' => now()->addDays(7)->toDateString(),
            'notice_period_days' => 3,
        ]);
        $contract->update(['status' => ContractStatus::Active->value, 'activated_at' => now()]);

        ContractMilestone::create([
            'contract_id' => $contract->id,
            'title' => 'Milestone pengingat',
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => MilestoneStatus::Pending->value,
        ]);

        $this->artisan('ctr:remind', ['--days' => 30])->assertSuccessful();

        // In-app: pembuat kontrak (admin) menerima notifikasi.
        $this->assertGreaterThan(
            0,
            PlatformNotification::where('user_id', $this->admin->id)->count(),
            'Notifikasi in-app tidak terkirim ke pembuat kontrak.'
        );

        // Outbox: event kontrak kedaluwarsa + milestone terekam.
        $this->assertGreaterThanOrEqual(
            1,
            OutboxMessage::where('event_type', 'contract.expiring')->count()
        );
        $this->assertGreaterThanOrEqual(
            1,
            OutboxMessage::where('event_type', 'contract.milestone_upcoming')->count()
        );

        $expiringCount = OutboxMessage::where('event_type', 'contract.expiring')->count();

        // Jalankan ulang: idempotency key deterministik → tidak menggandakan event.
        $this->artisan('ctr:remind', ['--days' => 30])->assertSuccessful();
        $this->assertSame(
            $expiringCount,
            OutboxMessage::where('event_type', 'contract.expiring')->count(),
            'Retry ctr:remind tidak boleh menggandakan event outbox.'
        );

        // Milestone reminder_sent tidak dikirim dua kali.
        $this->assertTrue(
            ContractMilestone::where('contract_id', $contract->id)->firstOrFail()->reminder_sent
        );
    }

    public function test_remind_command_handles_notice_period(): void
    {
        $contract = $this->makeContract([
            'end_date' => now()->addDays(40)->toDateString(),
            'notice_period_days' => 30, // notice date = +10 hari, di dalam horizon 30
        ]);
        $contract->update(['status' => ContractStatus::Active->value, 'activated_at' => now()]);

        $this->artisan('ctr:remind', ['--days' => 30])->assertSuccessful();

        $this->assertGreaterThanOrEqual(
            1,
            OutboxMessage::where('event_type', 'contract.notice_period')->count()
        );
    }

    // ── 29.1 Payment schedule / advance / retention ──────────────────────

    public function test_payment_schedule_apportions_contract_value_and_retention(): void
    {
        $contract = $this->makeContract([
            'total_value_idr' => 1_000_000,
            'advance_amount_idr' => 200_000,
            'retention_percent' => 10,
            'start_date' => now()->toDateString(),
        ]);

        $finance = app(ContractFinanceService::class);
        $schedules = $finance->buildSchedule($contract, ['count' => 4, 'interval_months' => 1]);

        $this->assertCount(5, $schedules); // advance + 4 termin
        $this->assertSame(200_000, $schedules[0]->amount_idr);
        $this->assertSame(0, $schedules[0]->retention_amount_idr);

        $terms = array_slice($schedules, 1);
        $this->assertSame(800_000, array_sum(array_map(fn ($s) => $s->amount_idr, $terms)));
        $this->assertSame(80_000, array_sum(array_map(fn ($s) => $s->retention_amount_idr, $terms)));

        // Retry generation idempoten: tidak membuat termin duplikat.
        $again = $finance->buildSchedule($contract, ['count' => 4, 'interval_months' => 1]);
        $this->assertCount(5, $again);
        $this->assertSame(5, $contract->paymentSchedules()->count());
    }

    public function test_advance_payment_posts_idempotently_and_caps_at_advance_amount(): void
    {
        $payer = User::factory()->create(['role' => 'customer']);
        app(TopUpAction::class)
            ->execute($payer, '2000000', 'contract_advance_topup');

        $contract = $this->makeContract([
            'total_value_idr' => 1_000_000,
            'advance_amount_idr' => 300_000,
        ]);
        $finance = app(ContractFinanceService::class);

        $finance->payAdvance($contract, $payer->id, 200_000, 'ctr-advance-1');
        $balanceAfterFirst = $payer->fresh()->walletBalance('IDR')->amount->toInt();
        $this->assertSame(1_800_000, $balanceAfterFirst);

        // Retry ledger posting key; state is locked and cumulative advance remains capped.
        try {
            $finance->payAdvance($contract->fresh(), $payer->id, 200_000, 'ctr-advance-1');
            $this->fail('Pembayaran advance yang sama tidak boleh mengurangi saldo lagi.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Sisa uang muka', $e->getMessage());
        }

        $this->assertSame($balanceAfterFirst, $payer->fresh()->walletBalance('IDR')->amount->toInt());
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_milestone_based_schedule_uses_milestone_amounts(): void
    {
        $contract = $this->makeContract(['total_value_idr' => 1_000_000]);
        ContractMilestone::create([
            'contract_id' => $contract->id, 'title' => 'M1', 'due_date' => now()->addMonth(),
            'status' => MilestoneStatus::Pending->value, 'amount_idr' => 400_000,
        ]);
        ContractMilestone::create([
            'contract_id' => $contract->id, 'title' => 'M2', 'due_date' => now()->addMonths(2),
            'status' => MilestoneStatus::Pending->value, 'amount_idr' => 600_000,
        ]);

        $schedules = app(ContractFinanceService::class)->buildSchedule($contract, ['by_milestone' => true]);
        $this->assertCount(2, $schedules);
        $this->assertSame(1_000_000, array_sum(array_map(fn ($s) => $s->amount_idr, $schedules)));
        $this->assertNotNull($schedules[0]->milestone_id);
    }

    // ── 29.2 Penalty formula / grace / cap ───────────────────────────────

    public function test_penalty_rule_applies_grace_basis_points_and_cap(): void
    {
        $contract = $this->makeContract();
        $rule = PenaltyRule::create([
            'contract_id' => $contract->id,
            'name' => '0.5% per day after grace',
            'unit' => PenaltyRule::UNIT_PERCENT_PER_DAY,
            'value' => 50, // 50bp = 0.5%/hari
            'cap_amount_idr' => 100_000,
            'grace_days' => 2,
            'is_active' => true,
        ]);

        $this->assertSame(0, $rule->computePenalty(2, 1_000_000));
        $this->assertSame(15_000, $rule->computePenalty(5, 1_000_000));
        $this->assertSame(100_000, $rule->computePenalty(100, 1_000_000));
    }

    // ── 29.3 Stored index and bounded escalation formula ─────────────────

    public function test_escalation_uses_stored_index_and_cap(): void
    {
        EscalationIndex::create([
            'code' => 'CPI_TEST', 'name' => 'Indeks Test', 'value' => 110,
            'observed_at' => now()->toDateString(), 'source' => 'simulasi',
        ]);

        $contract = $this->makeContract([
            'total_value_idr' => 1_000_000,
            'escalation_enabled' => true,
            'escalation_formula' => 'base * (index / index_base)',
            'escalation_index_code' => 'CPI_TEST',
            'escalation_index_base' => 100,
            'escalation_cap_percent' => 5,
        ]);

        $finance = app(ContractFinanceService::class);
        $preview = $finance->computeEscalation($contract);
        $this->assertTrue($preview['applied']);
        $this->assertEqualsWithDelta(1.05, $preview['factor'], 0.00001); // capped at 5%

        $applied = $finance->applyEscalation($contract);
        $this->assertSame(1_050_000, $applied['new_total_idr']);
        $this->assertSame(1_050_000, $contract->fresh()->total_value_idr);
    }

    // ── 29.4 Amendment appends version and recalculates unpaid schedules ──

    public function test_amendment_changes_value_appends_chain_and_rebuilds_unpaid_schedule(): void
    {
        $contract = $this->makeContract(['total_value_idr' => 1_000_000]);
        $contract->update(['status' => ContractStatus::Active->value, 'activated_at' => now()]);
        app(ContractFinanceService::class)->buildSchedule($contract, ['count' => 2]);
        $beforeVersions = $contract->versions()->count();

        $amendment = app(ContractAmendmentService::class)->amend($contract, [
            'total_value_idr' => 1_200_000,
            'reason' => 'Penambahan ruang lingkup',
            'kind' => 'addendum',
        ], 'Legal Test');

        $this->assertSame(1_200_000, $contract->fresh()->total_value_idr);
        $this->assertGreaterThan($beforeVersions, $contract->versions()->count());
        $this->assertTrue($amendment->schedule_recalculated);
        $this->assertSame(1_200_000, (int) $contract->paymentSchedules()->sum('amount_idr'));
    }

    // ── 29.5 Usage ledger idempotency and utilization thresholds ────────

    public function test_usage_sync_is_idempotent_and_flags_80_and_100_percent(): void
    {
        $contract = $this->makeContract(['total_value_idr' => 1_000_000]);
        $usage = app(ContractUsageService::class);

        $this->assertTrue($usage->recordUsage($contract, 'test_source', 999, 850_000, 'fixture'));
        $this->assertFalse($usage->recordUsage($contract, 'test_source', 999, 850_000, 'fixture replay'));
        $this->assertSame(850_000, (int) $contract->fresh()->used_value_idr);
        $this->assertSame('warning', $usage->utilization($contract->fresh())['status']);

        $this->assertTrue($usage->recordUsage($contract, 'test_source', 1000, 200_000, 'overrun'));
        $this->assertSame('exceeded', $usage->utilization($contract->fresh())['status']);

        $audit = app(ContractReportService::class)->audit();
        $this->assertTrue($audit['balanced']);
        $this->assertSame([], $audit['discrepancies']);
    }

    // ── 29.7 Risk scoring and compliance flags ───────────────────────────

    public function test_risk_scorer_flags_missing_confidentiality_force_majeure_and_arbitration(): void
    {
        $contract = $this->makeContract([
            'total_value_idr' => 2_000_000_000,
            'end_date' => now()->addDays(20)->toDateString(),
            'start_date' => now()->subYears(3)->toDateString(),
            'notice_period_days' => 15,
            'escalation_enabled' => false,
            'dispute_forum' => null,
            'arbitration_rules' => null,
        ]);
        $contract->update(['status' => ContractStatus::Active->value, 'activated_at' => now()]);

        $result = app(ContractRiskService::class)->score($contract, persist: false);
        // high_value(15) + long_term(15) + no_confidentiality(15)
        // + no_force_majeure(15) + near_expiry(10) = 70
        $this->assertGreaterThanOrEqual(70, $result['score']);
        $this->assertGreaterThanOrEqual(5, count($result['flags']));
    }

    // ── 29.8 Reports and ctr:audit ───────────────────────────────────────

    public function test_ctr_audit_command_balanced_when_usage_cache_matches(): void
    {
        $contract = $this->makeContract(['total_value_idr' => 1_000_000]);
        app(ContractUsageService::class)->recordUsage($contract, 'audit_fixture', 500, 250_000);
        $contract->update(['status' => ContractStatus::Active->value, 'activated_at' => now()]);

        $this->artisan('ctr:audit')->assertSuccessful();

        $reports = app(ContractReportService::class);
        $this->assertNotEmpty($reports->exposure()['by_type']);
        $this->assertSame(['current', '1_30', '31_60', 'over_60'], array_keys($reports->obligationAging()));
    }

    // ── 29.6 Contract rate card beats standard tariff ────────────────────

    public function test_contract_rate_card_overrides_standard_tariff_when_linked(): void
    {
        $shipper = User::factory()->create(['role' => 'shipper']);

        // Lokasi + dua rate card (standar 10k, kontrak 50k).
        $origin = Location::create([
            'code' => 'HUB-CON-OR', 'name' => 'Origin Kontrak', 'type' => 'hub',
            'city' => 'Banjarmasin', 'province' => 'Kalimantan Selatan',
            'country' => 'ID', 'lat_e6' => -3316694, 'lng_e6' => 114590111,
            'timezone' => 'Asia/Makassar', 'min_connection_minutes' => 60,
        ]);
        $dest = Location::create([
            'code' => 'HUB-CON-DS', 'name' => 'Destinasi Kontrak', 'type' => 'hub',
            'city' => 'Surabaya', 'province' => 'Jawa Timur',
            'country' => 'ID', 'lat_e6' => -7250445, 'lng_e6' => 112768845,
            'timezone' => 'Asia/Jakarta', 'min_connection_minutes' => 60,
        ]);

        // Hanya kartu kontrak pada lane ini: tanpa resolver, QuoteShipmentAction
        // akan gagal (NoRateCardFound) sebab tidak ada tarif standar yang cocok;
        // dengan resolver, kartu kontrak dipakai dan tarifnya berlaku.
        $contractCard = RateCard::create([
            'name' => 'Tarif Kontrak Khusus',
            'origin_location_id' => $origin->id, 'destination_location_id' => $dest->id,
            'service_level' => ServiceLevel::Regular,
            'mode' => TransportMode::ROAD,
            'min_charge_idr' => 50_000, 'valid_from' => '2026-01-01', 'valid_to' => null, 'is_active' => true,
        ]);
        RateBracket::create([
            'rate_card_id' => $contractCard->id, 'min_weight_kg' => 0,
            'max_weight_kg' => 10, 'rate_per_kg_idr' => 50_000, 'is_flat' => false,
        ]);

        // Tautan: pihak kedua kontrak ↔ party akun shipper ↔ kontrak menautkan rate card.
        $contract = $this->makeContract(['contract_type' => ContractType::Service->value]);
        $contract->update([
            'status' => ContractStatus::Active->value,
            'activated_at' => now(),
            'end_date' => now()->addMonths(6),
            'linked_rate_card_id' => $contractCard->id,
        ]);
        $secondParty = $contract->parties()->where('role', 'second_party')->first()->party_id;

        DB::table('lgx_shipper_accounts')->insert([
            'shipper_id' => $shipper->id, 'party_id' => $secondParty,
            'credit_limit_idr' => 50_000_000, 'payment_terms_days' => 30,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $resolver = new ContractRateResolver;
        $quoteAction = new QuoteShipmentAction(
            new ChargeableWeightCalculator,
            $resolver,
        );

        $quote = $quoteAction->execute(
            shipper: $shipper,
            originLocationId: $origin->id,
            destinationLocationId: $dest->id,
            serviceLevel: ServiceLevel::Regular,
            packages: [['weight_g' => 2000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100, 'description' => 'Test']],
        );

        // Pastikan resolver memilih rate card kontrak untuk shipper ini.
        $overrideId = $resolver->resolve($shipper->id, 'regular', 'road');
        $this->assertSame((int) $contractCard->id, $overrideId, 'Resolver harus memilih rate card kontrak.');

        // Quote terbentuk dari tarif kontrak: 2 kg x 50.000 = 100.000 (> min 50.000).
        $this->assertGreaterThanOrEqual(
            50_000,
            $quote->total_amount_idr,
            'Tarif kontrak (rate 50k/kg, min 50rb) yang dipakai, bukan tarif lain.'
        );
    }

    public function test_resolver_returns_null_without_active_contract(): void
    {
        $shipper = User::factory()->create(['role' => 'shipper']);
        $resolver = new ContractRateResolver;

        $this->assertNull($resolver->resolve($shipper->id, 'regular', 'road'));
    }
}
