<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyCarbonEsg;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Egy\Application\Actions\MatchCarbonOrdersAction;
use Modules\Egy\database\seeders\EgyRbacSeeder;
use Modules\Egy\Domain\Models\CarbonOrder;
use Modules\Egy\Domain\Models\CarbonTrade;
use Modules\Egy\Domain\StateMachines\CarbonOrderStateMachine;
use Tests\TestCase;

final class EgyCarbonOrderbookPriceTimeMatchingE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_carbon_orderbook_matching_posting_and_idempotency(): void
    {
        $this->seed();
        (new EgyRbacSeeder)->run();

        $action = app(MatchCarbonOrdersAction::class);

        // Buat Order Jual: 5.000 kg (5 ton) @ Rp 340.000 / ton (total Rp 1.700.000)
        $sellOrder = CarbonOrder::create([
            'order_number' => 'ORD-SELL-TEST-01',
            'party_id' => 1,
            'side' => 'SELL',
            'vintage_year' => '2025',
            'quantity_kg_co2e' => 5_000,
            'filled_kg_co2e' => 0,
            'limit_price_minor_per_ton' => 340_000,
            'status' => CarbonOrderStateMachine::STATUS_OPEN,
        ]);

        // Buat Order Beli: 5.000 kg (5 ton) @ Rp 350.000 / ton (cocok pada harga pasif 340.000)
        $buyOrder = CarbonOrder::create([
            'order_number' => 'ORD-BUY-TEST-01',
            'party_id' => 10,
            'side' => 'BUY',
            'vintage_year' => '2025',
            'quantity_kg_co2e' => 5_000,
            'filled_kg_co2e' => 0,
            'limit_price_minor_per_ton' => 350_000,
            'status' => CarbonOrderStateMachine::STATUS_OPEN,
        ]);

        // Eksekusi match action pertama kali
        $trades = $action->execute('TEST-MATCH-CYCLE-1');

        $this->assertCount(1, $trades);
        $trade = $trades[0];

        $this->assertSame(5_000, $trade->matched_kg_co2e);
        $this->assertSame(340_000, $trade->execution_price_minor_per_ton);
        $this->assertSame(1_700_000, $trade->total_amount_idr);
        $this->assertNotNull($trade->ledger_transaction_id);

        // Verifikasi status order terisi penuh
        $sellOrder->refresh();
        $buyOrder->refresh();
        $this->assertSame(CarbonOrderStateMachine::STATUS_FILLED, $sellOrder->status);
        $this->assertSame(CarbonOrderStateMachine::STATUS_FILLED, $buyOrder->status);
        $this->assertSame(5_000, $sellOrder->filled_kg_co2e);
        $this->assertSame(5_000, $buyOrder->filled_kg_co2e);

        // Verifikasi double-entry ledger balance: AR Buyer didebit Rp 1.700.000
        $buyerEntry = LedgerEntry::where('transaction_id', $trade->ledger_transaction_id)
            ->whereHas('account', fn ($q) => $q->where('code', 'ar:egy:customer:10:IDR'))
            ->first();

        $this->assertNotNull($buyerEntry);
        $this->assertSame(1_700_000, (int) abs((float) $buyerEntry->amount));

        // Verifikasi idempotensi: eksekusi ulang pada siklus yang sama tidak menduplikasi trade
        $repeatedTrades = $action->execute('TEST-MATCH-CYCLE-1');
        $this->assertCount(1, $repeatedTrades);
        $this->assertSame($trade->id, $repeatedTrades[0]->id);
        $this->assertSame(1, CarbonTrade::count());
    }
}
