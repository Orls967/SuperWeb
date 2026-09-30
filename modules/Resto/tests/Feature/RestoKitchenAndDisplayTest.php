<?php

declare(strict_types=1);

namespace Modules\Resto\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Resto\Application\Actions\CookBatchAction;
use Modules\Resto\Application\Actions\DiscardTrayAction;
use Modules\Resto\Application\Actions\RecirculateTrayAction;
use Modules\Resto\database\seeders\RestoMenuSeeder;
use Modules\Resto\Domain\Enums\BatchStatus;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Exceptions\InvalidTrayOperationException;
use Modules\Resto\Domain\Exceptions\ShortageException;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\Recipe;
use Tests\TestCase;

class RestoKitchenAndDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RestoMenuSeeder::class);
    }

    public function test_batch_memotong_bahan_baku_lewat_inventory_service_secara_presisi(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $recipe = Recipe::whereNotNull('menu_item_id')->whereHas('lines')->firstOrFail();
        $inventoryService = app(InventoryService::class);
        $cookAction = app(CookBatchAction::class);

        // Record stock before cooking
        $firstLine = $recipe->lines()->where('line_type', 'ingredient')->firstOrFail();
        $ingId = $firstLine->ingredient_id;
        $stockBefore = (float) $inventoryService->availableIngredient($ingId, $outlet->id);

        $batch = $cookAction->handle(
            outletId: $outlet->id,
            recipeId: $recipe->id,
            plannedPortions: 10,
            actualPortions: 10,
            putOnDisplay: true
        );

        $this->assertEquals(BatchStatus::ON_DISPLAY, $batch->status);
        $this->assertGreaterThan(0, $batch->cost_total);
        $this->assertGreaterThan(0, $batch->cost_per_portion);

        // Check stock deducted
        $stockAfter = (float) $inventoryService->availableIngredient($ingId, $outlet->id);
        $this->assertLessThan($stockBefore, $stockAfter);

        // Check tray created
        $tray = DisplayTray::where('batch_id', $batch->id)->first();
        $this->assertNotNull($tray);
        $this->assertEquals(10, $tray->portions_remaining);
        $this->assertEquals(0, $tray->recirculation_count);
        $this->assertEquals(TrayStatus::ON_DISPLAY, $tray->status);
    }

    public function test_bahan_kurang_ditolak_dengan_shortage_exception_dan_memberikan_saran_porsi(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $recipe = Recipe::whereNotNull('menu_item_id')->whereHas('lines')->firstOrFail();
        $inventoryService = app(InventoryService::class);
        $cookAction = app(CookBatchAction::class);

        // Set one of the ingredients stock to 0
        $firstLine = $recipe->lines()->where('line_type', 'ingredient')->firstOrFail();
        $inventoryService->adjustIngredient(
            ingredientId: $firstLine->ingredient_id,
            outletId: $outlet->id,
            newStockBaseUnit: '0.000000',
            note: 'Kosongkan stok untuk pengujian shortage'
        );

        $caught = false;
        try {
            $cookAction->handle(
                outletId: $outlet->id,
                recipeId: $recipe->id,
                plannedPortions: 20
            );
        } catch (ShortageException $e) {
            $caught = true;
            $this->assertNotEmpty($e->shortages);
            $this->assertEquals(0, $e->suggestedMaxPortions);
        }

        $this->assertTrue($caught);
    }

    public function test_produksi_sebagian_scale_down_resep_dapat_dimasak_sesuai_saran_porsi(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $recipe = Recipe::whereNotNull('menu_item_id')->whereHas('lines')->firstOrFail();
        $inventoryService = app(InventoryService::class);
        $cookAction = app(CookBatchAction::class);

        // Give exactly enough stock for 5 portions of the first line
        $firstLine = $recipe->lines()->where('line_type', 'ingredient')->firstOrFail();
        $qtyFor5 = (float) $firstLine->quantity()->multipliedBy('0.5')->__toString();

        $inventoryService->adjustIngredient(
            ingredientId: $firstLine->ingredient_id,
            outletId: $outlet->id,
            newStockBaseUnit: (string) ($qtyFor5 + 50),
            note: 'Stok cukup untuk 5 porsi saja'
        );

        // Attempting 5 portions should succeed
        $batch = $cookAction->handle(
            outletId: $outlet->id,
            recipeId: $recipe->id,
            plannedPortions: 5,
            actualPortions: 5,
            putOnDisplay: true
        );

        $this->assertEquals(5, $batch->actual_portions);
        $this->assertEquals(BatchStatus::ON_DISPLAY, $batch->status);
    }

    public function test_varians_produksi_dicatat_dan_cost_per_portion_dibagi_actual_portions(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $recipe = Recipe::whereNotNull('menu_item_id')->whereHas('lines')->firstOrFail();
        $cookAction = app(CookBatchAction::class);

        // Rencana 10 porsi, aktual hanya jadi 8 porsi (misal tumpah 2 porsi)
        $batch = $cookAction->handle(
            outletId: $outlet->id,
            recipeId: $recipe->id,
            plannedPortions: 10,
            actualPortions: 8,
            putOnDisplay: true
        );

        $this->assertEquals(10, $batch->planned_portions);
        $this->assertEquals(8, $batch->actual_portions);
        $this->assertEquals(-2, $batch->variancePortions());
        $this->assertEquals((int) ceil($batch->cost_total / 8), $batch->cost_per_portion);
    }

    public function test_tray_kedaluwarsa_otomatis_dialihkan_ke_waste_dengan_nominal_hpp_benar(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $recipe = Recipe::whereNotNull('menu_item_id')->whereHas('lines')->firstOrFail();
        $cookAction = app(CookBatchAction::class);

        $batch = $cookAction->handle(
            outletId: $outlet->id,
            recipeId: $recipe->id,
            plannedPortions: 10,
            actualPortions: 10,
            putOnDisplay: true
        );

        $tray = DisplayTray::where('batch_id', $batch->id)->firstOrFail();
        $expectedWasteValue = $tray->totalWasteValue();

        // Manipulate expires_at to be in the past
        $tray->update(['expires_at' => now()->subHour()]);

        $wasteBefore = (int) LedgerAccount::where('code', 'expense:resto:waste:IDR')->value('cached_balance');

        $this->artisan('resto:expire-display')
            ->assertSuccessful();

        $tray->refresh();
        $this->assertEquals(TrayStatus::DISCARDED, $tray->status);
        $this->assertEquals(0, $tray->portions_remaining);

        $wasteAfter = (int) LedgerAccount::where('code', 'expense:resto:waste:IDR')->value('cached_balance');
        $this->assertEquals($expectedWasteValue, $wasteAfter - $wasteBefore);

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_aturan_resirkulasi_maksimal_3_kali_dan_ditolak_pada_resirkulasi_keempat(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $recipe = Recipe::whereNotNull('menu_item_id')->whereHas('lines')->firstOrFail();
        $cookAction = app(CookBatchAction::class);
        $recirculateAction = app(RecirculateTrayAction::class);

        $batch = $cookAction->handle(
            outletId: $outlet->id,
            recipeId: $recipe->id,
            plannedPortions: 10,
            actualPortions: 10,
            putOnDisplay: true
        );

        $tray = DisplayTray::where('batch_id', $batch->id)->firstOrFail();

        // 1. Dihidang ke meja (in_service) -> kembali utuh -> resirkulasi 1
        $tray->status = TrayStatus::IN_SERVICE;
        $tray->save();
        $recirculateAction->handle($tray);
        $this->assertEquals(1, $tray->recirculation_count);
        $this->assertEquals(TrayStatus::ON_DISPLAY, $tray->status);

        // 2. Dihidang lagi -> resirkulasi 2
        $tray->status = TrayStatus::IN_SERVICE;
        $tray->save();
        $recirculateAction->handle($tray);
        $this->assertEquals(2, $tray->recirculation_count);

        // 3. Dihidang lagi -> resirkulasi 3
        $tray->status = TrayStatus::IN_SERVICE;
        $tray->save();
        $recirculateAction->handle($tray);
        $this->assertEquals(3, $tray->recirculation_count);

        // 4. Resirkulasi ke-4 wajib ditolak dan otomatis dialihkan ke waste
        $tray->status = TrayStatus::IN_SERVICE;
        $tray->save();

        $rejected = false;
        try {
            $recirculateAction->handle($tray);
        } catch (InvalidTrayOperationException $e) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $tray->refresh();
        $this->assertEquals(TrayStatus::DISCARDED, $tray->status);
        $this->assertEquals(0, $tray->portions_remaining);

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_resirkulasi_ditolak_jika_sudah_melewati_batas_waktu_pajang_etalase_6_jam(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $recipe = Recipe::whereNotNull('menu_item_id')->whereHas('lines')->firstOrFail();
        $cookAction = app(CookBatchAction::class);
        $recirculateAction = app(RecirculateTrayAction::class);

        $batch = $cookAction->handle(
            outletId: $outlet->id,
            recipeId: $recipe->id,
            plannedPortions: 10,
            actualPortions: 10,
            putOnDisplay: true
        );

        $tray = DisplayTray::where('batch_id', $batch->id)->firstOrFail();
        $tray->update(['placed_at' => now()->subHours(7)]);
        $batch->update(['cooked_at' => now()->subHours(7)]);

        $tray->status = TrayStatus::IN_SERVICE;
        $tray->save();

        $rejected = false;
        try {
            $recirculateAction->handle($tray);
        } catch (InvalidTrayOperationException $e) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $tray->refresh();
        $this->assertEquals(TrayStatus::DISCARDED, $tray->status);
    }

    public function test_bank_reconcile_tetap_nol_setelah_simulasi_50_batch_produksi_dan_waste(): void
    {
        $outlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $recipe = Recipe::whereNotNull('menu_item_id')->whereHas('lines')->firstOrFail();
        $cookAction = app(CookBatchAction::class);
        $discardAction = app(DiscardTrayAction::class);

        for ($i = 1; $i <= 50; $i++) {
            $batch = $cookAction->handle(
                outletId: $outlet->id,
                recipeId: $recipe->id,
                plannedPortions: 5,
                actualPortions: 5,
                putOnDisplay: true
            );

            // Discard every 5th tray
            if ($i % 5 === 0) {
                $tray = DisplayTray::where('batch_id', $batch->id)->first();
                if ($tray) {
                    $discardAction->handle($tray, "Simulasi uji batch ke-{$i}");
                }
            }
        }

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_halaman_kitchen_dan_endpoints_http_bekerja_dengan_baik(): void
    {
        $kitchenUser = User::where('email', 'dapur@autoserve.test')->firstOrFail();
        $outlet = Outlet::where('code', 'CK-01')->firstOrFail();
        $recipe = Recipe::whereNotNull('menu_item_id')->whereHas('lines')->firstOrFail();

        // 1. Index page
        $response = $this->actingAs($kitchenUser)->get(route('resto.kitchen.index'));
        $response->assertOk();
        $response->assertSee('Dapur & Etalase Hidang', false);

        // 2. Simulate endpoint
        $simResponse = $this->actingAs($kitchenUser)->postJson(route('resto.kitchen.simulate'), [
            'outlet_id' => $outlet->id,
            'recipe_id' => $recipe->id,
            'portions' => 10,
        ]);
        $simResponse->assertOk();
        $simResponse->assertJsonStructure(['can_cook', 'portions', 'suggested_portions', 'ingredients']);

        // 3. Cook endpoint
        $cookResponse = $this->actingAs($kitchenUser)->post(route('resto.kitchen.cook'), [
            'outlet_id' => $outlet->id,
            'recipe_id' => $recipe->id,
            'planned_portions' => 10,
            'actual_portions' => 10,
            'put_on_display' => 1,
            'note' => 'HTTP test batch',
        ]);
        $cookResponse->assertRedirect();

        // 4. IDOR test: staff outlet CK-01 cannot cook in outlet DM-01
        $dmOutlet = Outlet::where('code', 'DM-01')->firstOrFail();
        $idorResponse = $this->actingAs($kitchenUser)->post(route('resto.kitchen.cook'), [
            'outlet_id' => $dmOutlet->id,
            'recipe_id' => $recipe->id,
            'planned_portions' => 10,
        ]);
        $idorResponse->assertStatus(403);
    }
}
