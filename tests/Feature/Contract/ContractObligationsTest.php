<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Contract\Application\Services\ContractService;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Enums\ContractType;
use Modules\Contract\Domain\Enums\MilestoneStatus;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractAttachment;
use Modules\Contract\Domain\Models\ContractMilestone;
use Modules\Core\Contracts\DocumentStoreInterface;
use Modules\Core\Domain\Models\OutboxMessage;
use Modules\Core\Domain\Models\PlatformNotification;
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
}
