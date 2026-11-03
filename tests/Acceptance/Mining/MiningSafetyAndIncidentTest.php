<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Mining\Application\Actions\EnforceSafetyAreaStopAction;
use Modules\Mining\Application\Actions\IssueWorkPermitAction;
use Modules\Mining\Application\Actions\ReopenSafetyAreaAction;
use Modules\Mining\Domain\Models\MiningArea;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Tests\TestCase;

class MiningSafetyAndIncidentTest extends TestCase
{
    use RefreshDatabase;

    protected MiningSite $site;

    protected MiningPit $pit;

    protected MiningArea $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SUL-01',
            'name' => 'Pomalaa Nickel Site',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Kolaka',
            'status' => 'ACTIVE',
        ]);

        $this->pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_code' => 'PIT-KOL-01',
            'name' => 'Pit Kolaka 1',
            'target_production_ton' => 100000,
            'status' => 'ACTIVE',
        ]);

        $this->area = MiningArea::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_id' => $this->pit->id,
            'area_code' => 'AREA-DIG-01',
            'name' => 'Digging Sector 1',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_izin_kerja_k3_diterbitkan_lewat_approval_engine_bermasa_berlaku_t7(): void
    {
        $action = app(IssueWorkPermitAction::class);

        $res = $action->handle(
            siteId: $this->site->id,
            areaId: $this->area->id,
            workerPartyId: 'WRK-MINE-001',
            permitType: 'CONFINED_SPACE',
            validFrom: now()->toDateTimeString(),
            validUntil: now()->addHours(8)->toDateTimeString(),
            approverUserId: 10,
            idempotencyKey: 'min:ptw:issue:001'
        );

        $this->assertSame('APPROVED', $res->permit->approval_status);
        $this->assertDatabaseHas('min_work_permits', [
            'id' => $res->permit->id,
            'worker_party_id' => 'WRK-MINE-001',
            'approval_status' => 'APPROVED',
        ]);
    }

    public function test_izin_kerja_kedaluwarsa_menolak_aktivitas_operasional(): void
    {
        $permit = MiningWorkPermit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'area_id' => $this->area->id,
            'permit_number' => 'PTW-EXPIRED-01',
            'permit_type' => 'HOT_WORK',
            'worker_party_id' => 'WRK-MINE-002',
            'valid_from' => now()->subDays(2),
            'valid_until' => now()->subDay(),
            'approval_status' => 'APPROVED',
        ]);

        $this->assertTrue($permit->isExpired());
    }

    public function test_insiden_keparahan_tinggi_menghentikan_area_tambang_secara_otomatis(): void
    {
        $action = app(EnforceSafetyAreaStopAction::class);

        $res = $action->handle(
            areaId: $this->area->id,
            incidentSeverity: 'HIGH',
            incidentDescription: 'Highwall bench crack detected with continuous movement',
            reportedByUserId: 15,
            idempotencyKey: 'min:hse:stop:001'
        );

        $area = MiningArea::findOrFail($this->area->id);
        $this->assertSame('STOPPED', $area->status);
        $this->assertStringContainsString('Highwall bench crack', $area->stop_reason);
        $this->assertNotNull($area->stopped_at);

        $this->assertDatabaseHas('min_safety_incidents', [
            'id' => $res->incident->id,
            'area_id' => $this->area->id,
            'severity' => 'HIGH',
            'area_stopped' => true,
        ]);
    }

    public function test_area_dihentikan_menolak_dispatch_truk_dan_timbangan(): void
    {
        $this->area->update([
            'status' => 'STOPPED',
            'stop_reason' => 'Fatality near blast perimeter',
            'stopped_at' => now(),
        ]);

        $this->assertFalse($this->area->canOperate());
    }

    public function test_pembukaan_area_yang_dihentikan_memerlukan_approval_k3(): void
    {
        $this->area->update([
            'status' => 'STOPPED',
            'stop_reason' => 'Bench inspection required',
            'stopped_at' => now(),
        ]);

        $action = app(ReopenSafetyAreaAction::class);

        $action->handle(
            areaId: $this->area->id,
            approverUserId: 99,
            clearanceNotes: 'Bench geotechnical radar clearance confirmed stable by chief geologist.',
            idempotencyKey: 'min:hse:reopen:001'
        );

        $reopened = MiningArea::findOrFail($this->area->id);
        $this->assertSame('ACTIVE', $reopened->status);
        $this->assertNull($reopened->stopped_at);
    }

    public function test_log_audit_insiden_tercatat_lengkap(): void
    {
        $action = app(EnforceSafetyAreaStopAction::class);

        $res = $action->handle(
            areaId: $this->area->id,
            incidentSeverity: 'MEDIUM',
            incidentDescription: 'Excavator bucket hydraulic hose burst',
            reportedByUserId: 15,
            idempotencyKey: 'min:hse:stop:002'
        );

        $this->assertNotNull($res->incident->incident_code);
    }
}
