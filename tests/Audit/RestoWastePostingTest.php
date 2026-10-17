<?php

declare(strict_types=1);

namespace Tests\Audit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Resto\Application\Actions\DiscardTrayAction;
use Modules\Resto\Domain\Enums\BatchStatus;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\ProductionBatch;
use Modules\Resto\Domain\Models\Recipe;
use Tests\TestCase;

class RestoWastePostingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * M-R0-6 Kasus Wajib 4: Membuktikan alur pencatatan waste Resto pada DB bersih.
     * Menguji bahwa posting waste mendebit beban (expense:resto:waste:IDR)
     * dan mengkredit persediaan (inventory:resto:{code}:IDR) sesuai konvensi tanda tunggal (debit <= 0).
     *
     * @group audit-2026-10-18
     */
    public function test_resto_waste_posts_debit_to_expense_and_credit_to_inventory(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $outlet = Outlet::create([
            'code' => 'OUT-AUDIT-01',
            'name' => 'Outlet Audit Banjarmasin',
            'address' => 'Jl. Lambung Mangkurat No. 1',
            'phone' => '08123456789',
            'is_active' => true,
        ]);

        $category = \Modules\Resto\Domain\Models\MenuCategory::create([
            'name' => 'Makanan Utama',
            'sort' => 1,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'sku' => 'AUDIT-RESTO-01',
            'name' => 'Menu Audit',
            'slug' => 'menu-audit',
            'service_style' => \Modules\Resto\Domain\Enums\ServiceStyle::HIDANG,
            'base_price' => 20000,
            'is_active' => true,
        ]);

        $recipe = Recipe::create([
            'menu_item_id' => $menuItem->id,
            'sub_recipe_name' => 'Menu Audit Recipe',
            'yield_qty' => '1',
            'yield_unit' => 'portion',
            'expected_portions' => '5',
            'waste_percent' => '0',
            'version' => 1,
            'is_active' => true,
        ]);

        $batch = ProductionBatch::create([
            'outlet_id' => $outlet->id,
            'recipe_id' => $recipe->id,
            'batch_no' => 'BATCH-AUDIT-001',
            'planned_portions' => 5,
            'actual_portions' => 5,
            'cost_total' => 60000,
            'cost_per_portion' => 12000,
            'status' => BatchStatus::ON_DISPLAY->value,
            'cooked_at' => now(),
        ]);

        $tray = DisplayTray::create([
            'batch_id' => $batch->id,
            'outlet_id' => $outlet->id,
            'menu_item_id' => $menuItem->id,
            'tray_number' => 'TRAY-AUDIT-001',
            'portions_initial' => 2,
            'portions_remaining' => 2,
            'cost_per_portion' => 12000,
            'status' => TrayStatus::ON_DISPLAY->value,
            'displayed_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);

        // Eksekusi pembuangan tray (waste) 2 porsi x Rp 12.000 = Rp 24.000
        $discardAction = app(DiscardTrayAction::class);
        $discardAction->handle($tray, 'Basi / lewat masa pajang', $user->id);

        $wasteAccount = LedgerAccount::where('code', 'expense:resto:waste:IDR')->firstOrFail();
        $invAccount = LedgerAccount::where('code', "inventory:resto:{$outlet->code}:IDR")->firstOrFail();

        // 1. Beban bertambah pada sisi DEBIT (tanda negatif: -24.000)
        $wasteBalance = (int) $wasteAccount->cached_balance;
        $this->assertSame(-24000, $wasteBalance, 'Beban waste harus bertambah di sisi debit (<= 0)');
        $this->assertTrue(AccountKind::from($wasteAccount->kind)->isDebitNormal());

        // 2. Persediaan berkurang pada sisi KREDIT (tanda positif: +24.000)
        $invBalance = (int) $invAccount->cached_balance;
        $this->assertSame(24000, $invBalance, 'Pengurangan persediaan dicatat di sisi kredit (+)');

        // 3. Scanner normal balance: Akun expense tidak melanggar saldo normal
        $violations = \App\Quality\Ledger\LedgerNormalBalanceScanner::violations([$wasteAccount]);
        $this->assertNotContains('account:expense:resto:waste:IDR:debit_normal_has_credit_balance', $violations);
    }
}
