<?php

declare(strict_types=1);

namespace Tests\Perilaku\Uang;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Logistics\Application\Services\Reverse\ReverseLogisticsService;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('perilaku-uang')]
class LogisticsMoneyPathTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    public function test_logistics_circular_intake_posting_balance_and_idempotency(): void
    {
        $service = app(ReverseLogisticsService::class);
        $nominal = 1_250_000;

        // a) Buat order reverse logistics dan lakukan circular intake dengan nominal konkret 1.250.000
        $order = $service->triggerReverseOrderFromEvent(
            'CUSTOMER_RETURN',
            'SRC-'.uniqid(),
            'Customer Address Jakarta',
            'Central Remanufacturing Hub',
            [
                [
                    'category' => 'BATTERY',
                    'description' => 'Recycled EV Battery Cells',
                    'quantity' => 10,
                    'uom' => 'KG',
                    'recycling_target' => 'REMANUFACTURING',
                    'appraised_unit_price_idr' => 125_000, // 10 x 125.000 = 1.250.000
                ],
            ]
        );

        $this->assertEquals($nominal, $order->total_appraised_value_idr);

        // Proses intake memposting ledger
        $processed = $service->processCircularIntake($order);
        $this->assertEquals('PROCESSED', $processed->status);

        // b) Assert transaksi ledger
        $idempotencyKey = "CIR-INT-{$order->reverse_code}";
        $tx = LedgerTransaction::where('idempotency_key', $idempotencyKey)->with('entries.account')->first();

        $this->assertNotNull($tx, 'Transaksi ledger circular intake logistik harus tercatat');
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

        // d) Panggil 2x dengan permintaan identik -> idempoten
        $service->processCircularIntake($order);
        $txCount = LedgerTransaction::where('idempotency_key', $idempotencyKey)->count();
        $this->assertEquals(1, $txCount, 'Replay tidak boleh membuat transaksi ganda');
    }
}
