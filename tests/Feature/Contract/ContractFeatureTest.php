<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Contract\Application\Services\ContractService;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Enums\ContractType;
use Modules\Contract\Domain\Enums\MilestoneStatus;
use Modules\Contract\Domain\Models\ClauseTemplate;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractMilestone;
use Modules\Contract\Domain\Models\ContractVersion;
use Modules\Contract\Exceptions\ContractChainCorruptedException;
use Modules\Contract\Exceptions\InsufficientPartiesException;
use Modules\Contract\Exceptions\InvalidContractTransitionException;
use Modules\Contract\Exceptions\MissingTransitionReasonException;
use Modules\Contract\Exceptions\UnapprovedSignAttemptException;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;
use Tests\TestCase;

class ContractFeatureTest extends TestCase
{
    use RefreshDatabase;

    private ContractService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContractService::class);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function seedLe(): LegalEntity
    {
        return LegalEntity::create([
            'name' => 'Test Corp',
            'short_name' => 'TCORP',
            'entity_type' => 'company',
            'functional_currency' => 'IDR',
            'fiscal_year_start' => '01-01',
            'is_active' => true,
        ]);
    }

    private function seedParty(string $name): Party
    {
        return Party::create([
            'type' => 'company',
            'name' => $name,
            'name_normalized' => strtolower($name),
            'status' => 'verified',
            'is_active' => true,
        ]);
    }

    private function makeContract(LegalEntity $le, Party $p1, Party $p2, array $extra = []): Contract
    {
        return $this->service->createContract(array_merge([
            'legal_entity_id' => $le->id,
            'title' => 'Test Kontrak',
            'contract_type' => ContractType::Service->value,
            'total_value_idr' => 50_000_000,
            'parties' => [
                ['party_id' => $p1->id, 'role' => 'first_party',  'signing_order' => 1],
                ['party_id' => $p2->id, 'role' => 'second_party', 'signing_order' => 2],
            ],
            'created_by_name' => 'PHPUnit',
        ], $extra));
    }

    // ==========================================================================
    // ── (a) HAPPY PATH
    // ==========================================================================

    public function test_creates_contract_with_gapless_number_and_genesis_hash_chain(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT Vendor Utama');
        $p2 = $this->seedParty('PT Mitra Global');

        $contract = $this->makeContract($le, $p1, $p2, ['title' => 'Perjanjian Jasa IT']);

        $this->assertStringContainsString('CTR/', $contract->contract_number);
        $this->assertSame(ContractStatus::Draft, $contract->status);
        $this->assertSame(1, $contract->versions()->count());
        $this->assertSame(2, $contract->parties()->count());

        $v1 = $contract->versions()->first();
        $this->assertSame(1, $v1->sequence);
        $this->assertSame('creation', $v1->change_type);
        $this->assertStringStartsWith('GENESIS_CTR', $v1->prev_hash);
    }

    public function test_full_lifecycle_draft_review_negotiation_approved_signed_active(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT Alpha');
        $p2 = $this->seedParty('PT Beta');

        $contract = $this->makeContract($le, $p1, $p2);

        $contract = $this->service->transitionStatus($contract, ContractStatus::Review);
        $this->assertSame(ContractStatus::Review, $contract->status);

        $contract = $this->service->transitionStatus($contract, ContractStatus::Negotiation);
        $this->assertSame(ContractStatus::Negotiation, $contract->status);

        $contract = $this->service->transitionStatus($contract, ContractStatus::Approved);
        $this->assertSame(ContractStatus::Approved, $contract->status);

        $cp1 = $contract->parties()->where('role', 'first_party')->first();
        $cp2 = $contract->parties()->where('role', 'second_party')->first();
        $this->service->signContractParty($contract, $cp1, 'Direktur A', 'CEO');
        $this->service->signContractParty($contract, $cp2, 'Direktur B', 'GM');

        $contract->refresh();
        $this->assertSame(ContractStatus::Signed, $contract->status);
        $this->assertNotNull($contract->signed_at);

        $contract = $this->service->transitionStatus($contract, ContractStatus::Active);
        $this->assertSame(ContractStatus::Active, $contract->status);
        $this->assertNotNull($contract->activated_at);
    }

    public function test_appends_versions_and_verifies_hash_chain_integrity(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT X');
        $p2 = $this->seedParty('PT Y');

        $contract = $this->makeContract($le, $p1, $p2);

        $this->service->appendVersion($contract, 'Versi negosiasi v2 — perubahan klausa 3', 'negotiation', [], 'Legal Officer');
        $this->service->appendVersion($contract, 'Versi final v3 — disepakati semua pihak', 'amendment', [], 'Legal Manager');

        $contract->refresh();
        $this->assertSame(3, $contract->versions()->count());
        $this->assertTrue($this->service->verifyHashChain($contract));
    }

    public function test_version_diff_returns_correct_plus_minus_lines(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT Diff1');
        $p2 = $this->seedParty('PT Diff2');

        $contract = $this->makeContract($le, $p1, $p2);
        $this->service->appendVersion($contract, "Baris baru\nBaris berubah v2", 'negotiation');

        $diff = $this->service->getVersionDiff($contract, 1, 2);

        $this->assertSame(1, $diff['v1']);
        $this->assertSame(2, $diff['v2']);
        $this->assertStringContainsString('+', $diff['diff']);
    }

    public function test_clause_template_renders_placeholders_correctly(): void
    {
        $clause = ClauseTemplate::create([
            'code' => 'CL-TEST-RENDER',
            'title' => 'Test Render',
            'category' => 'general',
            'body_template' => 'Perjanjian antara {{first_party}} dan {{second_party}} mulai {{start_date}}.',
            'version' => 1,
            'is_standard' => true,
            'is_active' => true,
        ]);

        $rendered = $clause->render([
            'first_party' => 'PT Alfa',
            'second_party' => 'PT Beta',
            'start_date' => '1 Oktober 2026',
        ]);

        $this->assertStringContainsString('PT Alfa', $rendered);
        $this->assertStringContainsString('PT Beta', $rendered);
        $this->assertStringContainsString('1 Oktober 2026', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }

    public function test_milestone_can_be_completed_with_proof(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT Pihak1');
        $p2 = $this->seedParty('PT Pihak2');

        $contract = $this->makeContract($le, $p1, $p2);

        $ms = ContractMilestone::create([
            'contract_id' => $contract->id,
            'title' => 'Deliverable Q1',
            'due_date' => now()->addMonth()->toDateString(),
            'responsible_role' => 'second_party',
            'status' => MilestoneStatus::Pending->value,
            'amount_idr' => 10_000_000,
        ]);

        $ms->update([
            'status' => MilestoneStatus::Completed->value,
            'completed_at' => now(),
            'completion_notes' => 'Laporan diserahkan via email',
        ]);

        $ms->refresh();
        $this->assertSame(MilestoneStatus::Completed, $ms->status);
        $this->assertNotNull($ms->completed_at);
        $this->assertStringContainsString('email', $ms->completion_notes);
    }

    // ==========================================================================
    // ── (b) VALIDASI & OTORISASI
    // ==========================================================================

    public function test_throws_insufficient_parties_when_draft_has_one_party(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT Single');

        $contract = $this->service->createContract([
            'legal_entity_id' => $le->id,
            'title' => 'Kontrak Hanya 1 Pihak',
            'contract_type' => ContractType::Nda->value,
            'parties' => [['party_id' => $p1->id, 'role' => 'first_party']],
        ]);

        $this->expectException(InsufficientPartiesException::class);
        $this->service->transitionStatus($contract, ContractStatus::Review);
    }

    public function test_throws_missing_reason_when_terminating_without_reason(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT T1');
        $p2 = $this->seedParty('PT T2');

        $contract = $this->makeContract($le, $p1, $p2);
        $contract->update(['status' => ContractStatus::Active->value]);
        $contract->refresh();

        $this->expectException(MissingTransitionReasonException::class);
        $this->service->transitionStatus($contract, ContractStatus::Terminated);
    }

    public function test_throws_invalid_transition_for_illegal_state_jump(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT J1');
        $p2 = $this->seedParty('PT J2');

        $contract = $this->makeContract($le, $p1, $p2);

        $this->expectException(InvalidContractTransitionException::class);
        // Draft → Active is illegal
        $this->service->transitionStatus($contract, ContractStatus::Active);
    }

    public function test_throws_unapproved_sign_attempt_when_contract_not_approved(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT S1');
        $p2 = $this->seedParty('PT S2');

        $contract = $this->makeContract($le, $p1, $p2);
        $cp = $contract->parties()->first();

        $this->expectException(UnapprovedSignAttemptException::class);
        $this->service->signContractParty($contract, $cp, 'Direktur', 'CEO');
    }

    public function test_terminated_contract_cannot_transition_to_any_state(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT Fin1');
        $p2 = $this->seedParty('PT Fin2');

        $contract = $this->makeContract($le, $p1, $p2);
        $contract->update(['status' => ContractStatus::Terminated->value]);
        $contract->refresh();

        $this->expectException(InvalidContractTransitionException::class);
        $this->service->transitionStatus($contract, ContractStatus::Draft);
    }

    // ==========================================================================
    // ── (c) IDEMPOTENSI
    // ==========================================================================

    public function test_contract_version_is_append_only_update_throws(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT AO1');
        $p2 = $this->seedParty('PT AO2');

        $contract = $this->makeContract($le, $p1, $p2);
        $v1 = $contract->versions()->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/append-only/');
        $v1->update(['body' => 'TAMPERED']);
    }

    public function test_contract_version_is_append_only_delete_throws(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT Del1');
        $p2 = $this->seedParty('PT Del2');

        $contract = $this->makeContract($le, $p1, $p2);
        $v1 = $contract->versions()->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/append-only/');
        $v1->delete();
    }

    public function test_clause_first_or_create_is_idempotent(): void
    {
        $base = [
            'title' => 'Test Clause',
            'category' => 'general',
            'body_template' => 'Body text here.',
            'version' => 1,
            'is_standard' => true,
            'is_active' => true,
        ];

        $c1 = ClauseTemplate::firstOrCreate(['code' => 'CL-IDEMPOTENT-TEST'], $base);
        $c2 = ClauseTemplate::firstOrCreate(['code' => 'CL-IDEMPOTENT-TEST'], array_merge($base, ['title' => 'Should Not Change']));

        $this->assertSame($c1->id, $c2->id);
        $this->assertSame(1, ClauseTemplate::where('code', 'CL-IDEMPOTENT-TEST')->count());
    }

    // ==========================================================================
    // ── (e) EDGE CASES
    // ==========================================================================

    public function test_detects_corrupted_hash_chain_when_body_tampered_at_db_level(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT Tamper1');
        $p2 = $this->seedParty('PT Tamper2');

        $contract = $this->makeContract($le, $p1, $p2);
        $this->service->appendVersion($contract, 'Versi 2 valid', 'negotiation');

        DB::table('ctr_contract_versions')
            ->where('contract_id', $contract->id)
            ->where('sequence', 1)
            ->update(['body' => 'TAMPERED CONTENT']);

        $this->expectException(ContractChainCorruptedException::class);
        $this->service->verifyHashChain($contract->fresh());
    }

    public function test_auto_renew_false_has_no_renewal_period(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT NR1');
        $p2 = $this->seedParty('PT NR2');

        $contract = $this->service->createContract([
            'legal_entity_id' => $le->id,
            'title' => 'Kontrak No Renewal',
            'contract_type' => ContractType::Service->value,
            'auto_renew' => false,
            'renewal_period_months' => null,
            'parties' => [
                ['party_id' => $p1->id, 'role' => 'first_party'],
                ['party_id' => $p2->id, 'role' => 'second_party'],
            ],
        ]);

        $this->assertFalse($contract->auto_renew);
        $this->assertNull($contract->renewal_period_months);
    }

    public function test_contract_status_can_transition_to_covers_valid_and_invalid(): void
    {
        $this->assertTrue(ContractStatus::Draft->canTransitionTo(ContractStatus::Review));
        $this->assertTrue(ContractStatus::Active->canTransitionTo(ContractStatus::Suspended));
        $this->assertTrue(ContractStatus::Expired->canTransitionTo(ContractStatus::Renewed));

        $this->assertFalse(ContractStatus::Draft->canTransitionTo(ContractStatus::Active));
        $this->assertFalse(ContractStatus::Terminated->canTransitionTo(ContractStatus::Draft));
        $this->assertFalse(ContractStatus::Signed->canTransitionTo(ContractStatus::Draft));
        $this->assertFalse(ContractStatus::Renewed->canTransitionTo(ContractStatus::Active));
    }

    public function test_suspend_requires_reason_and_stores_it(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT Susp1');
        $p2 = $this->seedParty('PT Susp2');

        $contract = $this->makeContract($le, $p1, $p2);
        $contract->update(['status' => ContractStatus::Active->value]);
        $contract->refresh();

        $updated = $this->service->transitionStatus(
            $contract,
            ContractStatus::Suspended,
            'Pelanggaran SLA selama 2 bulan berturut-turut'
        );

        $this->assertSame(ContractStatus::Suspended, $updated->status);
        $this->assertStringContainsString('SLA', $updated->suspension_reason);
    }

    public function test_milestone_overdue_detection_works(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT OD1');
        $p2 = $this->seedParty('PT OD2');

        $contract = $this->makeContract($le, $p1, $p2);

        $ms = ContractMilestone::create([
            'contract_id' => $contract->id,
            'title' => 'Overdue Milestone',
            'due_date' => now()->subDays(10)->toDateString(),
            'responsible_role' => 'second_party',
            'status' => MilestoneStatus::Pending->value,
            'amount_idr' => 0,
        ]);

        $this->assertTrue($ms->isOverdue());

        $ms->update(['status' => MilestoneStatus::Completed->value, 'completed_at' => now()]);
        $ms->refresh();
        $this->assertFalse($ms->isOverdue());
    }

    public function test_terminate_with_reason_stores_terminated_at_and_reason(): void
    {
        $le = $this->seedLe();
        $p1 = $this->seedParty('PT Trm1');
        $p2 = $this->seedParty('PT Trm2');

        $contract = $this->makeContract($le, $p1, $p2);
        $contract->update(['status' => ContractStatus::Active->value]);
        $contract->refresh();

        $updated = $this->service->transitionStatus(
            $contract,
            ContractStatus::Terminated,
            'Pihak Kedua melanggar SLA kritis selama 3 bulan'
        );

        $this->assertSame(ContractStatus::Terminated, $updated->status);
        $this->assertNotNull($updated->terminated_at);
        $this->assertStringContainsString('SLA', $updated->termination_reason);
    }

    public function test_genesis_prev_hash_length_does_not_exceed_column_capacity(): void
    {
        $this->assertLessThanOrEqual(64, strlen(ContractVersion::GENESIS_HASH));
    }
}
