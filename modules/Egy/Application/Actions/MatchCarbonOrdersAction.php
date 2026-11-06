<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\CarbonOrder;
use Modules\Egy\Domain\Models\CarbonTrade;
use Modules\Egy\Domain\StateMachines\CarbonOrderStateMachine;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;
use Modules\Shared\Domain\Algorithms\Utility\CarbonOrderbookMatching;

/**
 * Mencocokkan pesanan kredit karbon terbuka di orderbook dan membukukan transaksi ke ledger.
 */
final class MatchCarbonOrdersAction
{
    public function __construct(
        private readonly CarbonOrderbookMatching $matchingEngine,
        private readonly CarbonOrderStateMachine $stateMachine,
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * @return array<CarbonTrade>
     */
    public function execute(string $idempotencyPrefix): array
    {
        $existingCycleTrades = CarbonTrade::where('trade_number', 'like', $idempotencyPrefix.'%')->get();
        if ($existingCycleTrades->isNotEmpty()) {
            return $existingCycleTrades->all();
        }

        $openBuyOrders = CarbonOrder::where('side', 'BUY')
            ->whereIn('status', [CarbonOrderStateMachine::STATUS_OPEN, CarbonOrderStateMachine::STATUS_PARTIALLY_FILLED])
            ->orderBy('limit_price_minor_per_ton', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        $openSellOrders = CarbonOrder::where('side', 'SELL')
            ->whereIn('status', [CarbonOrderStateMachine::STATUS_OPEN, CarbonOrderStateMachine::STATUS_PARTIALLY_FILLED])
            ->orderBy('limit_price_minor_per_ton', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($openBuyOrders->isEmpty() || $openSellOrders->isEmpty()) {
            return [];
        }

        $bids = $openBuyOrders->map(fn (CarbonOrder $o) => [
            'id' => (int) $o->id,
            'party_id' => (int) $o->party_id,
            'price_minor_per_ton' => (int) $o->limit_price_minor_per_ton,
            'quantity_kg_co2e' => (int) ($o->quantity_kg_co2e - $o->filled_kg_co2e),
            'timestamp_epoch' => (int) $o->created_at->timestamp,
        ])->all();

        $asks = $openSellOrders->map(fn (CarbonOrder $o) => [
            'id' => (int) $o->id,
            'party_id' => (int) $o->party_id,
            'price_minor_per_ton' => (int) $o->limit_price_minor_per_ton,
            'quantity_kg_co2e' => (int) ($o->quantity_kg_co2e - $o->filled_kg_co2e),
            'timestamp_epoch' => (int) $o->created_at->timestamp,
        ])->all();

        $matchResult = $this->matchingEngine->match($bids, $asks);
        $executedTrades = [];

        foreach ($matchResult->matchedTrades as $idx => $tradeData) {
            $buyOrder = CarbonOrder::findOrFail($tradeData['buyer_order_id']);
            $sellOrder = CarbonOrder::findOrFail($tradeData['seller_order_id']);

            $tradeNumber = sprintf('%s-TRD-%d-%d-%d', $idempotencyPrefix, $buyOrder->id, $sellOrder->id, $idx + 1);

            // Periksa apakah trade sudah ada (idempoten)
            $existing = CarbonTrade::where('trade_number', $tradeNumber)->first();
            if ($existing !== null) {
                $executedTrades[] = $existing;

                continue;
            }

            // Ledger posting double-entry
            $matchedKg = (int) $tradeData['matched_kg_co2e'];
            $totalAmountIdr = (int) $tradeData['trade_value_idr'];
            $platformFeeIdr = (int) intdiv($totalAmountIdr * 100, 10000); // 1% platform commission
            $netSellerIdr = $totalAmountIdr - $platformFeeIdr;

            $buyerArAccount = EgyAccounts::customerReceivable((string) $buyOrder->party_id);
            $sellerRevenueAccount = 'egy:carbon_credit_sales_revenue:IDR';
            $feeRevenueAccount = 'egy:carbon_exchange_fee_revenue:IDR';

            $postingLines = Posting::lines()
                ->debit($buyerArAccount->code, $totalAmountIdr)
                ->credit($sellerRevenueAccount, $netSellerIdr)
                ->credit($feeRevenueAccount, $platformFeeIdr);

            $idempotencyKey = sprintf('%s:CARBON-TRADE:%s', $idempotencyPrefix, $tradeNumber);

            $tx = $this->ledgerService->post(new PostingDTO(
                type: EgyTransactionTypes::EGY_GRID_BILLING->value,
                description: sprintf('Eksekusi Perdagangan Karbon %s (%d kg CO2e)', $tradeNumber, $matchedKg),
                idempotencyKey: $idempotencyKey,
                entries: $postingLines->toEntries('IDR'),
                referenceType: 'CARBON_TRADE',
                referenceId: (string) $buyOrder->id,
            ));

            $trade = CarbonTrade::create([
                'trade_number' => $tradeNumber,
                'buy_order_id' => $buyOrder->id,
                'sell_order_id' => $sellOrder->id,
                'buyer_party_id' => $buyOrder->party_id,
                'seller_party_id' => $sellOrder->party_id,
                'matched_kg_co2e' => $matchedKg,
                'execution_price_minor_per_ton' => $tradeData['execution_price_minor'],
                'total_amount_idr' => $totalAmountIdr,
                'platform_fee_idr' => $platformFeeIdr,
                'ledger_transaction_id' => $tx->id,
                'settlement_status' => 'settled',
                'certificate_hash' => $matchResult->certificateHash,
            ]);

            // Perbarui kuantitas terisi dan status order beli
            $buyOrder->filled_kg_co2e += $matchedKg;
            if ($buyOrder->filled_kg_co2e >= $buyOrder->quantity_kg_co2e) {
                $buyOrder->status = $this->stateMachine->transition(
                    $buyOrder->status,
                    CarbonOrderStateMachine::STATUS_FILLED
                );
            } else {
                $buyOrder->status = $this->stateMachine->transition(
                    $buyOrder->status,
                    CarbonOrderStateMachine::STATUS_PARTIALLY_FILLED
                );
            }
            $buyOrder->save();

            // Perbarui order jual
            $sellOrder->filled_kg_co2e += $matchedKg;
            if ($sellOrder->filled_kg_co2e >= $sellOrder->quantity_kg_co2e) {
                $sellOrder->status = $this->stateMachine->transition(
                    $sellOrder->status,
                    CarbonOrderStateMachine::STATUS_FILLED
                );
            } else {
                $sellOrder->status = $this->stateMachine->transition(
                    $sellOrder->status,
                    CarbonOrderStateMachine::STATUS_PARTIALLY_FILLED
                );
            }
            $sellOrder->save();

            $executedTrades[] = $trade;
        }

        return $executedTrades;
    }
}
