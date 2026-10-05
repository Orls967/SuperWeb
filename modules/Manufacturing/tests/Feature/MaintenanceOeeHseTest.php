<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Manufacturing\Application\Services\HseService;
use Modules\Manufacturing\Application\Services\MaintenanceService;
use Modules\Manufacturing\Application\Services\ManufacturingService;
use Modules\Manufacturing\Application\Services\ProductionService;
use Modules\Manufacturing\Domain\Models\DowntimeLog;
use Modules\Manufacturing\Domain\Models\MaintenanceOrder;
use Modules\Manufacturing\Domain\Models\OeeSummary;
use Modules\Manufacturing\Domain\Models\OperationReport;
use Modules\Manufacturing\Domain\Models\WorkCenter;
use Tests\TestCase;

/**
 * Regresi Fase 40: OEE A×P×Q, WO pemeliharaan (idempoten alarm),
 * suku cadang, pareto/backlog, sensor threshold, K3 insiden + izin
 * kerja four-eyes & masa berlaku, energi per order.
 */
class MaintenanceOeeHseTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceService $maintenance;

    private HseService $hse;

    private ManufacturingService $mfg;

    private User $admin;

    private WorkCenter $wc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->maintenance = app(MaintenanceService::class);
        $this->hse = app(HseService::class);
        $this->mfg = app(ManufacturingService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();

        $plant = $this->mfg->createPlant(['code' => 'PLT-OEE', 'name' => 'Pabrik OEE', 'type' => 'factory']);
        $this->wc = WorkCenter::create([
            'plant_id' => $plant->id, 'code' => 'WC-OEE', 'name' => 'Mesin OEE', 'kind' => 'machine',
            'capacity_per_hour' => 60, 'efficiency_percent' => 100,
            'machine_cost_per_hour_idr' => 100_000, 'labor_cost_per_hour_idr' => 50_000,
            'overhead_per_hour_idr' => 25_000,
        ]);
    }

    // ── 40.1 OEE ─────────────────────────────────────────────────────────

    public function test_oee_availability_performance_quality_math(): void
    {
        $date = now()->toDateString();

        // Downtime 60 menit dari 480 → availability = 420/480.
        DowntimeLog::create([
            'work_center_id' => $this->wc->id, 'reason_code' => 'machine_down',
            'started_at' => now()->setTime(8, 0), 'ended_at' => now()->setTime(9, 0), 'minutes' => 60,
        ]);

        // Laporan operasi 420 menit, baik 40, scrap 2.
        OperationReport::create([
            'production_order_id' => $this->makeOrderId(), 'work_center_id' => $this->wc->id,
            'sequence' => 1, 'started_at' => now()->setTime(9, 0), 'finished_at' => now()->setTime(16, 0),
            'duration_minutes' => 420, 'qty_good' => 40, 'qty_scrap' => 2,
        ]);

        $result = $this->maintenance->computeOee($this->wc, $date, 480);

        $expectedAvailability = (420 / 480) * 100;
        $expectedQuality = (40 / 42) * 100;
        // Ideal cycle = 3600/60 = 60 detik/unit → 420 menit × 60 unit = ideal 420 menit → P = 100%.
        $expectedPerformance = ((60 * 42) / 60 / 420) * 100;

        $this->assertEqualsWithDelta($expectedAvailability, $result['availability'], 0.5);
        $this->assertEqualsWithDelta($expectedQuality, $result['quality'], 0.5);
        $this->assertEqualsWithDelta($expectedPerformance, $result['performance'], 0.5);
        $this->assertEqualsWithDelta(
            ($expectedAvailability / 100) * ($expectedPerformance / 100) * ($expectedQuality / 100) * 100,
            $result['oee'], 0.5
        );

        // Idempoten: hitung ulang menyegarkan row yang sama.
        $again = $this->maintenance->computeOee($this->wc, $date, 480);
        $this->assertSame($result['summary']->id, $again['summary']->id);
        $this->assertSame(1, OeeSummary::count());
    }

    private function makeOrderId(): string
    {
        $production = app(ProductionService::class);
        $material = $this->mfg->createMaterial(['code' => 'FG-OEE', 'name' => 'Jadi OEE', 'kind' => 'finished', 'base_uom' => 'pcs']);
        $order = $production->createProductionOrder([
            'material_id' => $material->id, 'qty' => 42, 'due_date' => now()->toDateString(),
        ], $this->admin);

        return $order->id;
    }

    // ── 40.2 WO pemeliharaan idempoten ──────────────────────────────────

    public function test_maintenance_order_transitions_and_alarm_is_idempotent(): void
    {
        $wo = $this->maintenance->createMaintenanceOrder($this->wc, 'Mesin berisik', $this->admin, [
            'priority' => 'high', 'trigger_key' => 'daily-check-WC-OEE',
        ]);
        $this->assertStringStartsWith('MWO/', $wo->number);

        // Trigger sama → WO yang sama (idempoten).
        $again = $this->maintenance->createMaintenanceOrder($this->wc, 'Duplikat', $this->admin, [
            'trigger_key' => 'daily-check-WC-OEE',
        ]);
        $this->assertSame($wo->id, $again->id);
        $this->assertSame(1, MaintenanceOrder::count());

        // Transisi guard: WO masih open → completed langsung ditolak.
        try {
            $this->maintenance->transitionMaintenance($wo, 'completed');
            $this->fail('Langsung open→completed harus ditolak (perlu in_progress).');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak sah', $e->getMessage());
        }

        // Jalur sah: open → in_progress → completed.
        $this->maintenance->transitionMaintenance($wo, 'in_progress');
        $this->assertSame('in_progress', $wo->fresh()->status);
        $done = $this->maintenance->transitionMaintenance($wo->fresh(), 'completed');
        $this->assertNotNull($done->completed_at);

        // Replay completed → idempoten.
        $again = $this->maintenance->transitionMaintenance($done, 'completed');
        $this->assertSame($done->id, $again->id);
    }

    // ── 40.4 Sensor → WO otomatis ───────────────────────────────────────

    public function test_sensor_threshold_breaches_create_alarm_wo_once(): void
    {
        $result = $this->maintenance->recordSensorReading(
            $this->wc, 'T-01', 'temperature', 95.0, $this->admin, 90.0, null
        );
        $this->assertTrue($result['alarm']);
        $this->assertNotNull($result['maintenance']);
        $this->assertSame('predictive', $result['maintenance']->kind);
        $this->assertStringStartsWith('sensor:', (string) $result['maintenance']->trigger_key);

        // Bacaan dalam ambang → tidak ada alarm baru.
        $ok = $this->maintenance->recordSensorReading(
            $this->wc, 'T-01', 'temperature', 70.0, $this->admin, 90.0, null
        );
        $this->assertFalse($ok['alarm']);
        $this->assertNull($ok['maintenance']);
        $this->assertSame(1, MaintenanceOrder::count());

        // Metrik tak dikenal ditolak.
        try {
            $this->maintenance->recordSensorReading($this->wc, 'X', 'humidity', 50.0, $this->admin);
            $this->fail('Metrik tak dikenal harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Metrik sensor', $e->getMessage());
        }
    }

    // ── 40.3/40.5 Suku cadang, pareto, backlog ──────────────────────────

    public function test_equipment_parts_pareto_and_backlog(): void
    {
        $part = $this->maintenance->addEquipmentPart($this->wc, [
            'part_code' => 'BRS-01', 'name' => 'Bearing', 'min_stock' => 2, 'unit_cost_idr' => 150_000,
        ]);
        $wo = $this->maintenance->createMaintenanceOrder($this->wc, 'Ganti bearing', $this->admin);

        $usage = $this->maintenance->consumePart($wo, $part, 1);
        $this->assertSame(150_000, (int) $usage->cost_idr);
        $this->assertSame(150_000, (int) $wo->fresh()->parts_cost_idr);

        DowntimeLog::create(['work_center_id' => $this->wc->id, 'reason_code' => 'material_wait', 'started_at' => now(), 'minutes' => 30]);
        DowntimeLog::create(['work_center_id' => $this->wc->id, 'reason_code' => 'machine_down', 'started_at' => now(), 'minutes' => 90]);
        DowntimeLog::create(['work_center_id' => $this->wc->id, 'reason_code' => 'machine_down', 'started_at' => now(), 'minutes' => 60]);

        $pareto = $this->maintenance->downtimePareto();
        $this->assertSame('machine_down', $pareto[0]['reason']);
        $this->assertSame(150, $pareto[0]['minutes']);
        $this->assertEqualsWithDelta(100.0, $pareto[1]['cumulative_percent'], 0.01);

        $backlog = $this->maintenance->maintenanceBacklog();
        $this->assertSame(1, $backlog['normal']['count'] + $backlog['high']['count'] + $backlog['low']['count'] + $backlog['critical']['count']);

        // Qty nol ditolak.
        try {
            $this->maintenance->consumePart($wo, $part, 0);
            $this->fail('Qty 0 harus ditolak.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    // ── 40.6 K3: insiden & izin ─────────────────────────────────────────

    public function test_incident_workflow_requires_investigation_before_close(): void
    {
        $incident = $this->hse->report([
            'title' => 'Tumpahan oli di line 2', 'kind' => 'near_miss', 'severity' => 'moderate',
            'work_center_id' => $this->wc->id, 'due_date' => now()->addDays(7)->toDateString(),
        ], $this->admin);

        $this->assertStringStartsWith('HSE/', $incident->number);
        $this->assertSame('reported', $incident->status);

        try {
            $this->hse->close($incident);
            $this->fail('Tutup sebelum investigasi harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('diinvestigasi', $e->getMessage());
        }

        $investigated = $this->hse->investigate($incident, 'Peluit longgar pada guard', 'Kencangkan & audit mingguan');
        $this->assertSame('action', $investigated->status);

        $closed = $this->hse->close($investigated);
        $this->assertSame('closed', $closed->status);
        $this->assertNotNull($closed->closed_at);

        // Idempoten.
        $again = $this->hse->close($closed);
        $this->assertSame('closed', $again->status);
    }

    public function test_work_permit_requires_four_eyes_and_validity_window(): void
    {
        $permit = $this->hse->requestPermit($this->wc->id, [
            'permit_type' => 'hot_work', 'hazards' => 'Percikan api', 'controls' => 'Fire watch',
            'valid_until' => now()->addDay()->toDateTimeString(),
        ], $this->admin);

        $this->assertStringStartsWith('PTW/', $permit->number);
        $this->assertSame('pending', $permit->status);
        $this->assertNotNull($permit->approval_id);
        $this->assertFalse($permit->isActiveAt());

        // Masa berlaku tidak valid ditolak.
        try {
            $this->hse->requestPermit($this->wc->id, [
                'permit_type' => 'confined_space', 'valid_from' => '2026-10-10 08:00:00',
                'valid_until' => '2026-10-09 08:00:00',
            ], $this->admin);
            $this->fail('valid_until sebelum valid_from harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('masa berlaku', mb_strtolower($e->getMessage()));
        }

        // Four-eyes: pengaju tidak boleh menyetujui sendiri.
        try {
            $this->hse->approvePermit($permit, $this->admin);
            $this->fail('Four-eyes dilanggar.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Four-Eyes', $e->getMessage());
        }

        $reviewer = User::factory()->create(['role' => 'admin']);
        $active = $this->hse->approvePermit($permit->fresh(), $reviewer);
        $this->assertSame('active', $active->status);
        $this->assertTrue($active->isActiveAt());

        // Izin tidak berlaku di luar jendela → assertPermitValid melempar.
        $active->status = 'active';
        $active->valid_until = now()->subMinute();
        $active->save();
        try {
            $this->hse->assertPermitValid($active->fresh());
            $this->fail('Izin kadaluarsa harus ditolak.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->assertSame(1, $this->hse->expirePermits());
    }

    public function test_resource_intensity_per_order(): void
    {
        $production = app(ProductionService::class);
        $material = $this->mfg->createMaterial(['code' => 'FG-ESG', 'name' => 'Jadi ESG', 'kind' => 'finished', 'base_uom' => 'pcs']);
        $order = $production->createProductionOrder([
            'material_id' => $material->id, 'qty' => 10, 'due_date' => now()->toDateString(),
        ], $this->admin);

        $this->maintenance->recordResourceUsage($order, 'electricity_kwh', 50, $this->admin, ['cost_idr' => 75_000]);
        $this->maintenance->recordResourceUsage($order, 'water_liter', 200, $this->admin);

        $intensity = collect($this->maintenance->resourceIntensity($order));
        // qty_completed = 0 → divisor = max(1) → per_unit = total.
        $this->assertSame(50.0, $intensity->firstWhere('resource', 'electricity_kwh')['per_unit']);

        $order->update(['qty_completed' => 10]);
        $intensity = collect($this->maintenance->resourceIntensity($order));
        $this->assertEqualsWithDelta(5.0, $intensity->firstWhere('resource', 'electricity_kwh')['per_unit'], 0.01);

        try {
            $this->maintenance->recordResourceUsage($order, 'unknown', 1, $this->admin);
            $this->fail('Sumber daya tak dikenal harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Sumber daya', $e->getMessage());
        }
    }

    public function test_maintenance_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('manufacturing.maintenance.index'))
            ->assertOk()
            ->assertSee('Pemeliharaan');
    }
}
