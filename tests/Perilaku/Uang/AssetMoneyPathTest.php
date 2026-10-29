<?php

declare(strict_types=1);

namespace Tests\Perilaku\Uang;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Asset\Application\Services\AssetService;
use Modules\Asset\Domain\Models\AssetCategory;
use Modules\Asset\Domain\Models\AssetLocation;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerTransaction;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('perilaku-uang')]
class AssetMoneyPathTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    public function test_asset_acquisition_ledger_posting_balance_and_idempotency(): void
    {
        $service = app(AssetService::class);
        $nominal = 1_250_000;

        $category = AssetCategory::firstOrCreate(
            ['code' => 'TEST_EQ'],
            ['name' => 'Peralatan Uji', 'useful_life_months' => 36, 'depreciation_method' => 'straight_line']
        );

        $location = AssetLocation::firstOrCreate(
            ['code' => 'LOC_TEST'],
            ['name' => 'Lokasi Uji']
        );

        $user = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

        // a) Jalankan alur akuisisi aset dengan nominal konkret 1.250.000
        $asset = $service->register([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'name' => 'Mesin Uji Presisi',
            'acquisition_date' => now()->toDateString(),
            'acquisition_cost_idr' => $nominal,
            'landed_cost_idr' => 0,
            'source_type' => 'direct',
        ], $user, postLedger: true);

        // b) Assert transaksi ledger akuisisi
        $idempotencyKey = 'ast:acquire:'.$asset->id;
        $tx = LedgerTransaction::where('idempotency_key', $idempotencyKey)->with('entries.account')->first();

        $this->assertNotNull($tx, 'Transaksi ledger akuisisi aset harus terbentuk');
        $this->assertGreaterThanOrEqual(2, $tx->entries->count());

        // Σ entri = 0 per aset
        $sum = $tx->entries->sum(fn ($e) => (float) $e->amount);
        $this->assertEqualsWithDelta(0.0, $sum, 0.0001, 'Σ entri per aset harus = 0');

        // Akun di COA
        foreach ($tx->entries as $e) {
            $this->assertNotNull($e->account);
            $this->assertNotEmpty($e->account->code);
            $kind = $e->account->kind instanceof AccountKind ? $e->account->kind : AccountKind::tryFrom((string) $e->account->kind);
            if ($kind) {
                $this->assertContains($kind->normalSide(), ['debit', 'credit', 'free']);
            }
        }

        // d) Replay posting idempoten lewat ensureCapitalized
        $service->ensureCapitalized($asset);
        $txCount = LedgerTransaction::where('idempotency_key', $idempotencyKey)->count();
        $this->assertEquals(1, $txCount, 'Replay tidak boleh membuat transaksi ganda');
    }
}
