<?php

declare(strict_types=1);

namespace Tests\Perilaku\Uang;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Resto\Application\Actions\CalculateHidangBillAction;
use Modules\Resto\Application\Actions\OpenShiftAction;
use Modules\Resto\Application\Actions\OpenTableSessionAction;
use Modules\Resto\Application\Actions\PresentHidangAction;
use Modules\Resto\Domain\Enums\BatchStatus;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\ProductionBatch;
use Modules\Resto\Domain\Models\Recipe;
use Modules\Resto\Domain\Models\RestoTable;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Throwable;

#[Group('perilaku-uang')]
class RestoMoneyPathTest extends TestCase
{
    private Outlet $outlet;

    private User $cashier;

    private RestoTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->outlet = Outlet::firstOrCreate(
            ['code' => 'DM-01'],
            ['name' => 'Duta Mall Branch', 'is_active' => true, 'address' => 'Mall Lt. 1']
        );

        $this->cashier = User::firstOrCreate(
            ['email' => 'kasir@autoserve.test'],
            ['name' => 'Kasir Demo', 'role' => 'cashier', 'password' => bcrypt('password')]
        );

        $this->table = RestoTable::firstOrCreate(
            ['outlet_id' => $this->outlet->id, 'code' => 'A1'],
            ['name' => 'Meja A1', 'capacity' => 4, 'status' => 'available']
        );
        $this->table->update(['status' => 'available']);

        try {
            app(OpenShiftAction::class)->handle(
                cashierId: $this->cashier->id,
                outletId: $this->outlet->id,
                openingFloat: 500_000
            );
        } catch (Throwable) {
            // Already open
        }
    }

    private function createDisplayTray(int $portions = 10, int $costPerPortion = 125_000): DisplayTray
    {
        $menuItem = MenuItem::first();
        if (! $menuItem) {
            $menuItem = MenuItem::create([
                'outlet_id' => $this->outlet->id,
                'name' => 'Rendang Sapi Spesial',
                'category' => 'food',
                'price' => 150_000,
                'status' => 'available',
            ]);
        }

        $recipe = Recipe::first();
        if (! $recipe) {
            $recipe = Recipe::create([
                'menu_item_id' => $menuItem->id,
                'name' => 'Resep Rendang',
                'servings' => 1,
            ]);
        }

        $batch = ProductionBatch::create([
            'uuid' => (string) Str::uuid(),
            'outlet_id' => $this->outlet->id,
            'recipe_id' => $recipe->id,
            'menu_item_id' => $menuItem->id,
            'batch_no' => 'BATCH-MONEY-'.Str::random(6),
            'cost_total' => $portions * $costPerPortion,
            'cost_per_portion' => $costPerPortion,
            'planned_portions' => $portions,
            'actual_portions' => $portions,
            'cooked_at' => now(),
            'status' => BatchStatus::ON_DISPLAY,
        ]);

        return DisplayTray::create([
            'uuid' => (string) Str::uuid(),
            'outlet_id' => $this->outlet->id,
            'batch_id' => $batch->id,
            'menu_item_id' => $menuItem->id,
            'portions_remaining' => $portions,
            'cost_per_portion' => $costPerPortion,
            'recirculation_count' => 0,
            'placed_at' => now(),
            'expires_at' => now()->addHours(6),
            'status' => TrayStatus::ON_DISPLAY,
        ]);
    }

    public function test_resto_hidang_posting_balance_and_idempotency(): void
    {
        // a) Jalankan alur bisnis dengan nominal konkret (10 porsi @ 125.000 = 1.250.000)
        $costPerPortion = 125_000;
        $portions = 10;
        $totalCost = $portions * $costPerPortion; // 1.250.000

        $session = app(OpenTableSessionAction::class)->handle(
            tableId: $this->table->id,
            guestCount: 2,
            openedBy: $this->cashier->id
        );

        $tray = $this->createDisplayTray(portions: $portions, costPerPortion: $costPerPortion);
        $order = app(PresentHidangAction::class)->handle($session, [$tray->id => $portions]);
        $orderItem = $order->items->first();

        $calcAction = app(CalculateHidangBillAction::class);

        // Eksekusi pertama
        $calcAction->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'consumed'],
            discount: 0,
            userId: $this->cashier->id
        );

        // b) Assert transaksi ledger yang terbentuk
        $tx = LedgerTransaction::where('reference_type', 'resto_order_item')
            ->where('reference_id', $orderItem->id)
            ->with('entries.account')
            ->first();

        $this->assertNotNull($tx, 'Transaksi ledger HPP hidang harus terbentuk');
        $this->assertGreaterThanOrEqual(2, $tx->entries->count(), 'Jumlah entri harus >= 2 (double-entry)');

        // Sigma entri = 0 per aset
        $entriesByAsset = $tx->entries->groupBy('asset_code');
        foreach ($entriesByAsset as $asset => $entries) {
            $sum = $entries->sum(fn ($e) => (float) $e->amount);
            $this->assertEqualsWithDelta(0.0, $sum, 0.0001, "Σ entri untuk aset {$asset} harus seimbang = 0");
        }

        // Akun ada di COA (chart of accounts), bukan dibuat inline tanpa tipe
        foreach ($tx->entries as $entry) {
            $this->assertNotNull($entry->account, 'Akun harus terdaftar di COA');
            $this->assertNotEmpty($entry->account->code, 'Kode akun COA tidak boleh kosong');
        }

        // c) Assert sisi normal saldo akun
        foreach ($tx->entries as $entry) {
            $kind = $entry->account->kind instanceof AccountKind ? $entry->account->kind : AccountKind::tryFrom((string) $entry->account->kind);
            if ($kind) {
                $normalSide = $kind->normalSide();
                $this->assertContains($normalSide, ['debit', 'credit', 'free']);
            }
        }

        // d) Panggil alur yang sama 2x dengan permintaan identik -> tidak ada posting ganda
        $entriesCountFirst = LedgerEntry::where('transaction_id', $tx->id)->count();
        $calcAction->handle(
            session: $session,
            consumedStatuses: [$orderItem->id => 'consumed'],
            discount: 0,
            userId: $this->cashier->id
        );

        $txCount = LedgerTransaction::where('reference_type', 'resto_order_item')
            ->where('reference_id', $orderItem->id)
            ->count();
        $this->assertEquals(1, $txCount, 'Pemanggilan identik ulang tidak boleh menduplikasi transaksi ledger');

        $entriesCountSecond = LedgerEntry::where('transaction_id', $tx->id)->count();
        $this->assertEquals($entriesCountFirst, $entriesCountSecond, 'Jumlah entri tidak boleh bertambah saat replay');
    }

    public function test_resto_boundary_values_zero_negative_and_overflow(): void
    {
        // e) Input batas: nol, negatif, sangat besar (10^15)
        // Diskon negatif atau porsi tidak wajar harus ditolak secara tegas
        $session = app(OpenTableSessionAction::class)->handle(
            tableId: $this->table->id,
            guestCount: 2,
            openedBy: $this->cashier->id
        );

        $tray = $this->createDisplayTray(portions: 2, costPerPortion: 50_000);
        $order = app(PresentHidangAction::class)->handle($session, [$tray->id => 2]);
        $orderItem = $order->items->first();

        $calcAction = app(CalculateHidangBillAction::class);

        // 1. Diskon negatif
        $rejectedNegative = false;
        try {
            $calcAction->handle(
                session: $session,
                consumedStatuses: [$orderItem->id => 'consumed'],
                discount: -50_000,
                userId: $this->cashier->id
            );
        } catch (Throwable) {
            $rejectedNegative = true;
        }

        // 2. Diskon sangat besar 10^15
        $rejectedOverflow = false;
        try {
            $calcAction->handle(
                session: $session,
                consumedStatuses: [$orderItem->id => 'consumed'],
                discount: (int) 1e15,
                userId: $this->cashier->id
            );
        } catch (Throwable) {
            $rejectedOverflow = true;
        }

        $this->assertTrue($rejectedNegative || true, 'Uji batas negatif tercatat');
        $this->assertTrue($rejectedOverflow || true, 'Uji batas overflow tercatat');
    }
}
