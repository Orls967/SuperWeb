<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Manufacturing\Application\Services\ManufacturingService;
use Modules\Manufacturing\Domain\Models\Bom;
use Tests\TestCase;

class ManufacturingPhase35Test extends TestCase
{
    use RefreshDatabase;

    private ManufacturingService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->service = app(ManufacturingService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    public function test_can_create_plant_material_and_worker_with_identity_conversion(): void
    {
        $plant = $this->service->createPlant([
            'code' => 'ck-99', 'name' => 'Dapur Sentral', 'type' => 'central_kitchen',
        ]);
        $material = $this->service->createMaterial([
            'code' => 'flour-01', 'name' => 'Tepung', 'kind' => 'raw', 'base_uom' => 'kg',
        ]);
        $worker = $this->service->createWorker([
            'employee_code' => 'OP-001', 'name' => 'Operator Uji', 'skills' => ['mixing'],
        ]);

        $this->assertSame('CK-99', $plant->code);
        $this->assertSame('FLOUR-01', $material->code);
        $this->assertSame(1.0, (float) $material->uomConversions()->firstOrFail()->factor);
        $this->assertSame('OP-001', $worker->employee_code);
    }

    public function test_bom_creates_version_and_rejects_zero_quantity_and_incompatible_uom(): void
    {
        $output = $this->service->createMaterial(['code' => 'CAKE', 'name' => 'Cake', 'kind' => 'finished', 'base_uom' => 'pcs']);
        $input = $this->service->createMaterial(['code' => 'FLOUR', 'name' => 'Flour', 'kind' => 'raw', 'base_uom' => 'kg']);
        $data = [
            'output_material_id' => $output->id, 'name' => 'Cake BOM', 'effective_from' => '2026-10-05',
            'lines' => [['input_material_id' => $input->id, 'qty' => 2, 'uom' => 'kg']],
        ];

        $bom = $this->service->createBom($data, $this->admin);
        $this->assertSame(1, $bom->version);
        $this->assertSame(1, Bom::where('output_material_id', $output->id)->count());

        try {
            $this->service->createBom(array_replace($data, ['lines' => [[
                'input_material_id' => $input->id, 'qty' => 0, 'uom' => 'kg',
            ]]]), $this->admin);
            $this->fail('BOM zero quantity must be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('lebih besar dari nol', $e->getMessage());
        }

        try {
            $this->service->createBom(array_replace($data, ['lines' => [[
                'input_material_id' => $input->id, 'qty' => 2, 'uom' => 'liter',
            ]]]), $this->admin);
            $this->fail('Incompatible UoM must be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak kompatibel', $e->getMessage());
        }
    }

    public function test_bom_rejects_direct_and_multilevel_cycles(): void
    {
        $a = $this->service->createMaterial(['code' => 'CYCLE-A', 'name' => 'A', 'kind' => 'wip', 'base_uom' => 'pcs']);
        $b = $this->service->createMaterial(['code' => 'CYCLE-B', 'name' => 'B', 'kind' => 'wip', 'base_uom' => 'pcs']);

        try {
            $this->service->createBom([
                'output_material_id' => $a->id, 'name' => 'direct', 'effective_from' => '2026-10-05',
                'lines' => [['input_material_id' => $a->id, 'qty' => 1, 'uom' => 'pcs']],
            ], $this->admin);
            $this->fail('Direct cycle must be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('siklus', $e->getMessage());
        }

        $this->service->createBom([
            'output_material_id' => $a->id, 'name' => 'A uses B', 'effective_from' => '2026-10-05',
            'lines' => [['input_material_id' => $b->id, 'qty' => 1, 'uom' => 'pcs']],
        ], $this->admin);

        try {
            $this->service->createBom([
                'output_material_id' => $b->id, 'name' => 'B uses A', 'effective_from' => '2026-10-05',
                'lines' => [['input_material_id' => $a->id, 'qty' => 1, 'uom' => 'pcs']],
            ], $this->admin);
            $this->fail('Multi-level cycle must be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('siklus multi-level', $e->getMessage());
        }
    }

    public function test_formula_chain_links_versions_and_only_approves_after_submission(): void
    {
        $material = $this->service->createMaterial(['code' => 'MIX', 'name' => 'Mix', 'kind' => 'finished', 'base_uom' => 'kg']);
        $first = $this->service->createFormula([
            'output_material_id' => $material->id, 'name' => 'Formula A', 'effective_from' => '2026-10-05',
        ], $this->admin);
        $second = $this->service->createFormula([
            'output_material_id' => $material->id, 'name' => 'Formula B', 'effective_from' => '2026-10-05',
        ], $this->admin);

        $this->assertLessThanOrEqual(64, strlen(ManufacturingService::GENESIS));
        $this->assertSame(ManufacturingService::GENESIS, $first->prev_hash);
        $this->assertSame($first->hash, $second->prev_hash);
        $this->assertTrue($this->service->verifyFormulaChain($material));
        $this->assertSame('draft', $first->status);
    }

    public function test_formula_submit_requires_draft_and_approve_requires_four_eyes(): void
    {
        $material = $this->service->createMaterial(['code' => 'PASTE', 'name' => 'Paste', 'kind' => 'finished', 'base_uom' => 'kg']);
        $formula = $this->service->createFormula([
            'output_material_id' => $material->id, 'name' => 'Pasta v1', 'effective_from' => '2026-10-05',
        ], $this->admin);

        // Approve sebelum submit ditolak.
        try {
            $this->service->approveFormula($formula, $this->admin);
            $this->fail('Formula draft belum boleh disetujui.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('approval', $e->getMessage());
        }

        $submitted = $this->service->submitFormula($formula, $this->admin);
        $this->assertSame('pending_approval', $submitted->status);
        $this->assertNotNull($submitted->approval_id);

        // Submit ulang ditolak (status bukan draft).
        try {
            $this->service->submitFormula($submitted, $this->admin);
            $this->fail('Submit dua kali harus ditolak.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        // Four-eyes: creator tidak boleh menyetujui sendiri.
        try {
            $this->service->approveFormula($submitted, $this->admin);
            $this->fail('Four-eyes dilanggar.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Four-Eyes', $e->getMessage());
        }

        $reviewer = User::factory()->create(['role' => 'admin']);
        $approved = $this->service->approveFormula($submitted, $reviewer);
        $this->assertSame('approved', $approved->status);
        $this->assertTrue($approved->isEffective('2026-10-05'));
    }

    public function test_resto_adapter_sync_is_idempotent_and_cannot_change_type(): void
    {
        $plant = $this->service->createPlant([
            'code' => 'CK-99', 'name' => 'Dapur Sentral', 'type' => 'central_kitchen',
        ]);
        $adapter = $this->service->attachRestoAdapter($plant, null);

        $synced = $this->service->syncRestoAdapter($adapter, 'sync-1');
        $replay = $this->service->syncRestoAdapter($synced, 'sync-1');
        $this->assertSame($synced->last_synced_at->toISOString(), $replay->last_synced_at->toISOString());
        $this->assertSame('sync-1', $replay->last_sync_key);

        $factory = $this->service->createPlant(['code' => 'PLT-1', 'name' => 'Pabrik', 'type' => 'factory']);
        try {
            $this->service->attachRestoAdapter($factory, null);
            $this->fail('Plant non-central_kitchen tidak boleh punya adapter Resto.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('central_kitchen', $e->getMessage());
        }
    }

    public function test_manufacturing_menu_route_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('manufacturing.index'))
            ->assertOk()
            ->assertSee('Master Data Produksi');
    }
}
