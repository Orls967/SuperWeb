<?php

declare(strict_types=1);

namespace Tests\Acceptance\Port;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Port\Application\Actions\AllocateBerthAction;
use Modules\Port\Application\Actions\GenerateTerminalBillAction;
use Modules\Port\Application\Actions\RecordGatePassAction;
use Modules\Port\Domain\Models\Berth;
use Modules\Port\Domain\Models\GatePass;
use Modules\Port\Domain\Models\VesselCall;
use Modules\Port\Domain\Models\YardBlock;
use Modules\Port\Domain\Models\YardSlot;
use Modules\Port\Tests\Fixtures\PortAuditCorruptionFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortTerminalAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountsSeeder::class);
    }

    /**
     * Test 1: Akses semua grup rute dalam satu test (dataset role)
     */
    #[DataProvider('roleAccessProvider')]
    public function test_akses_semua_grup_rute_port_dengan_dataset_role(string $role, int $expectedStatus): void
    {
        Berth::firstOrCreate(['code' => 'B01'], ['name' => 'Berth 1', 'length_meters' => 200, 'max_draft_meters' => 14.5]);
        YardBlock::firstOrCreate(['code' => 'YB-A'], ['name' => 'Yard A', 'capacity_teu' => 500, 'block_type' => 'STANDARD']);

        $routes = ['/port', '/port/berths', '/port/yard', '/port/billing'];

        if ($role === 'guest') {
            foreach ($routes as $route) {
                $this->get($route)->assertRedirect('/login');
            }

            return;
        }

        $user = User::factory()->create(['role' => $role]);

        foreach ($routes as $route) {
            $response = $this->actingAs($user)->get($route);
            $response->assertStatus($expectedStatus);
        }
    }

    public static function roleAccessProvider(): array
    {
        return [
            'admin_access' => ['admin', 200],
            'operator_access' => ['port_operator', 200],
            'agent_access' => ['shipping_line_agent', 200],
            'driver_forbidden' => ['driver', 403],
            'guest_redirect' => ['guest', 302],
        ];
    }

    /**
     * Test 2: Alur utama alokasi tambatan berth discrete scheduling dan lifecycle vessel call
     */
    public function test_alur_utama_alokasi_tambatan_diskrit_branch_and_bound_dan_vessel_call(): void
    {
        $b1 = Berth::create(['code' => 'B01', 'name' => 'Berth 01', 'length_meters' => 300, 'max_draft_meters' => 15.0]);
        $b2 = Berth::create(['code' => 'B02', 'name' => 'Berth 02', 'length_meters' => 300, 'max_draft_meters' => 15.0]);

        $calls = [
            VesselCall::create(['call_number' => 'VC-01', 'vessel_name' => 'MV One', 'imo_number' => 'IMO9001', 'line_party_id' => 10, 'eta' => now()->startOfDay()->addHours(1), 'duration_hours' => 40, 'weight' => 1]),
            VesselCall::create(['call_number' => 'VC-02', 'vessel_name' => 'MV Two', 'imo_number' => 'IMO9002', 'line_party_id' => 10, 'eta' => now()->startOfDay()->addHours(1), 'duration_hours' => 16, 'weight' => 2]),
            VesselCall::create(['call_number' => 'VC-03', 'vessel_name' => 'MV Three', 'imo_number' => 'IMO9003', 'line_party_id' => 20, 'eta' => now()->startOfDay()->addHours(2), 'duration_hours' => 24, 'weight' => 1]),
            VesselCall::create(['call_number' => 'VC-04', 'vessel_name' => 'MV Four', 'imo_number' => 'IMO9004', 'line_party_id' => 20, 'eta' => now()->startOfDay()->addHours(3), 'duration_hours' => 12, 'weight' => 3]),
            VesselCall::create(['call_number' => 'VC-05', 'vessel_name' => 'MV Five', 'imo_number' => 'IMO9005', 'line_party_id' => 30, 'eta' => now()->startOfDay()->addHours(4), 'duration_hours' => 20, 'weight' => 2]),
        ];

        $callIds = array_map(fn ($c) => $c->id, $calls);

        $action = app(AllocateBerthAction::class);
        $result = $action->execute($callIds);

        $this->assertTrue($result['is_exact']);
        $this->assertSame(15, $result['objective']);
        $this->assertTrue($result['certificate']['is_valid']);

        $refreshedCalls = VesselCall::whereIn('id', $callIds)->get();
        foreach ($refreshedCalls as $call) {
            $this->assertNotNull($call->berth_id);
            $this->assertSame('SCHEDULED', $call->status);
            $this->assertIsArray($call->schedule_certificate);
        }
    }

    /**
     * Test 3: Invarian kontainer yard append-only dan pencegahan gate-in ganda
     */
    public function test_invarian_yard_append_only_dan_pencegahan_gate_in_ganda(): void
    {
        $action = app(RecordGatePassAction::class);
        $containerNo = 'CONT-INVAR-001';
        $linePartyId = 55;

        // 1. Gate In Pertama
        $gateInPass = $action->gateIn($containerNo, $linePartyId, 'B 1234 XY');
        $this->assertSame('IN', $gateInPass->pass_type);
        $this->assertSame('COMPLETED', $gateInPass->status);

        $activeSlot = YardSlot::where('container_no', $containerNo)->where('status', 'IN_YARD')->first();
        $this->assertNotNull($activeSlot);

        // 2. Upaya Gate In kedua ditolak
        $duplicateRejected = false;
        try {
            $action->gateIn($containerNo, $linePartyId, 'B 5678 ZZZ');
        } catch (DomainException $e) {
            $duplicateRejected = true;
            $this->assertStringContainsString('Gate-in ganda ditolak', $e->getMessage());
        }
        $this->assertTrue($duplicateRejected);

        // 3. Gate Out dan verifikasi invarian
        $gateOutPass = $action->gateOut($containerNo, $linePartyId, 'B 9999 OUT');
        $this->assertSame('OUT', $gateOutPass->pass_type);
        $this->assertSame('COMPLETED', $gateOutPass->status);

        $activeSlotAfter = YardSlot::where('container_no', $containerNo)->where('status', 'IN_YARD')->first();
        $this->assertNull($activeSlotAfter);

        $inCount = GatePass::where('pass_type', 'IN')->count();
        $outCount = GatePass::where('pass_type', 'OUT')->count();
        $yardCount = YardSlot::where('status', 'IN_YARD')->count();

        $this->assertSame($outCount + $yardCount, $inCount);
    }

    /**
     * Test 4: Penerbitan tagihan terminal terintegrasi (THC, dockage, storage, D&D via K5) dan posting double-entry ledger
     */
    public function test_penerbitan_tagihan_terminal_terintegrasi_dan_ledger_posting(): void
    {
        $berth = Berth::create(['code' => 'B-MAIN', 'name' => 'Main Berth', 'length_meters' => 250, 'max_draft_meters' => 14.0]);
        $call = VesselCall::create([
            'call_number' => 'VC-BILL-001',
            'vessel_name' => 'CMA CGM Titan',
            'imo_number' => 'IMO998877',
            'line_party_id' => 77,
            'eta' => now()->subDays(7),
            'duration_hours' => 36,
            'berth_id' => $berth->id,
            'status' => 'AT_BERTH',
        ]);

        $action = app(GenerateTerminalBillAction::class);
        $bill = $action->execute(vesselCallId: $call->id, containerCount: 10, dwellDays: 8);

        $this->assertSame('ISSUED', $bill->status);
        $this->assertGreaterThan(0, $bill->amount_idr);
        $this->assertSame(15_000_000, $bill->thc_amount_idr);
        $this->assertSame(1_800_000, $bill->dockage_amount_idr);
        $this->assertSame(800_000, $bill->storage_amount_idr);
        $this->assertGreaterThan(0, $bill->dnd_amount_idr);
        $this->assertNotNull($bill->ledger_journal_id);

        $arBalance = LedgerAccount::where('code', "ar:prt:line:{$call->line_party_id}:IDR")->value('cached_balance');
        $this->assertSame(-$bill->amount_idr, (int) $arBalance);
    }

    /**
     * Test 5: IDOR line party isolation & pencegahan double billing
     */
    public function test_idor_line_party_isolation_dan_pencegahan_double_billing(): void
    {
        $berth = Berth::create(['code' => 'B-IDOR', 'name' => 'Berth IDOR', 'length_meters' => 200, 'max_draft_meters' => 14.0]);

        $agent1 = User::factory()->create(['role' => 'shipping_line_agent']);
        $agent2 = User::factory()->create(['role' => 'shipping_line_agent']);

        $call1 = VesselCall::create([
            'call_number' => 'VC-PARTY-1',
            'vessel_name' => 'Maersk Alpha',
            'imo_number' => 'IMO11111',
            'line_party_id' => $agent1->id,
            'duration_hours' => 24,
            'berth_id' => $berth->id,
        ]);

        $call2 = VesselCall::create([
            'call_number' => 'VC-PARTY-2',
            'vessel_name' => 'MSC Beta',
            'imo_number' => 'IMO22222',
            'line_party_id' => $agent2->id,
            'duration_hours' => 24,
            'berth_id' => $berth->id,
        ]);

        $action = app(GenerateTerminalBillAction::class);
        $bill1 = $action->execute($call1->id);
        $bill2 = $action->execute($call2->id);

        // Uji IDOR isolasi pada view billing: Agent 1 hanya dapat melihat tagihannya sendiri
        $res1 = $this->actingAs($agent1)->get('/port/billing');
        $res1->assertStatus(200);
        $res1->assertSee($bill1->bill_number);
        $res1->assertDontSee($bill2->bill_number);

        // Uji pencegahan double billing
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('sudah pernah diterbitkan');
        $action->execute($call1->id);
    }

    /**
     * Test 6: Audit dua sumber prt:audit verifikasi rekonsiliasi bersih dan deteksi korupsi
     */
    public function test_audit_dua_sumber_port_bersih_dan_deteksi_anomali(): void
    {
        $action = app(RecordGatePassAction::class);
        $action->gateIn('CONT-AUD-1', 10, 'B 1111 AA');
        $action->gateIn('CONT-AUD-2', 10, 'B 2222 BB');
        $action->gateOut('CONT-AUD-1', 10, 'B 3333 CC');

        // Audit awal harus bersih
        $this->artisan('prt:audit')
            ->expectsOutputToContain('STATUS: BERSIH (0 selisih)')
            ->assertExitCode(0);

        // Distorsi invarian gerakan yard
        PortAuditCorruptionFixture::corruptYardMovement();

        // Audit setelah distorsi harus mendeteksi selisih
        $this->artisan('prt:audit')
            ->expectsOutputToContain('DITEMUKAN ANOMALI')
            ->assertExitCode(1);
    }
}
