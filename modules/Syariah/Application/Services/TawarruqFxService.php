<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Islamic\TawarruqFx;
use Modules\Shared\Domain\Algorithms\Islamic\TawarruqFxCertificateVerifier;
use Modules\Syariah\Domain\Enums\IslamicTradeStatus;
use Modules\Syariah\Domain\Models\SybCommodityLot;
use Modules\Syariah\Domain\Models\SybTawarruqOrder;
use Modules\Syariah\Domain\Models\SybTawarruqTransaction;
use Modules\Syariah\Domain\Models\SybTradeAuditLog;
use Modules\Syariah\Ledger\SyariahAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

final class TawarruqFxService
{
    public function __construct(
        private readonly ShariahLedgerPoster $ledgerPoster,
        private readonly TawarruqFx $tawarruqAlgorithm,
        private readonly TawarruqFxCertificateVerifier $certificateVerifier,
    ) {}

    public function executeTawarruqFx(
        string $customerPartyRef,
        string $sourceCurrency,
        int $sourcePrincipalCents,
        string $targetCurrency,
        int $spotRateBps,
        int $tenorDays,
        int $markupRateBps,
        int $brokerFeeCents,
        string $exchangeName = 'Bursa ICDX Syariah'
    ): SybTawarruqOrder {
        if ($sourcePrincipalCents <= 0 || $spotRateBps <= 0) {
            throw new InvalidArgumentException('Nominal pokok dan kurs spot tawarruq harus positif.');
        }

        SyariahAccounts::ensureAccountsExist();

        return DB::transaction(function () use (
            $customerPartyRef,
            $sourceCurrency,
            $sourcePrincipalCents,
            $targetCurrency,
            $spotRateBps,
            $tenorDays,
            $markupRateBps,
            $brokerFeeCents,
            $exchangeName
        ) {
            $count = SybTawarruqOrder::count() + 1;
            $orderNumber = sprintf('TWQ-%s-%04d', date('Ym'), $count);
            $serialNumber = sprintf('LOT-%s-%04d-%s', strtoupper($sourceCurrency), $count, bin2hex(random_bytes(4)));

            // 1. Dapatkan atau daftarkan lot komoditas bursa fisik yang terisolasi
            $commodityLot = SybCommodityLot::create([
                'lot_number' => 'LOT-EXCH-'.uniqid(),
                'exchange_name' => $exchangeName,
                'commodity_type' => 'METALS_ALUMINUM_GRADE_A',
                'physical_serial_number' => $serialNumber,
                'warehouse_location' => 'WH-ROTTERDAM-SYARIAH-01',
                'unit_quantity' => max(1, (int) intdiv($sourcePrincipalCents, 100_000_000)),
                'acquisition_cost_cents' => $sourcePrincipalCents,
                'lot_status' => 'ACQUIRED',
                'assigned_order_ref' => $orderNumber,
            ]);

            // 2. Kalkulasi algoritma bersertifikat B8
            $now = CarbonImmutable::now();
            $fxResult = $this->tawarruqAlgorithm->calculate(
                orderNumber: $orderNumber,
                sourceCurrency: $sourceCurrency,
                sourcePrincipalCents: $sourcePrincipalCents,
                targetCurrency: $targetCurrency,
                spotRateBps: $spotRateBps,
                tenorDays: $tenorDays,
                markupRateBps: $markupRateBps,
                brokerFeeCents: $brokerFeeCents,
                commodityLotSerial: $commodityLot->physical_serial_number,
                exchangeName: $exchangeName,
                evaluatedAt: $now
            );

            // Verifikasi integritas sertifikat
            $this->certificateVerifier->verify($fxResult);

            $order = SybTawarruqOrder::create([
                'commodity_lot_id' => $commodityLot->id,
                'order_number' => $orderNumber,
                'customer_party_ref' => $customerPartyRef,
                'source_currency' => $sourceCurrency,
                'source_amount_cents' => $sourcePrincipalCents,
                'target_currency' => $targetCurrency,
                'target_amount_cents' => $fxResult->targetReceivedCents,
                'spot_rate_bps' => $spotRateBps,
                'tenor_days' => $tenorDays,
                'markup_rate_bps' => $markupRateBps,
                'markup_profit_cents' => $fxResult->markupProfitCents,
                'broker_fee_cents' => $brokerFeeCents,
                'total_deferred_price_cents' => $fxResult->totalDeferredPriceCents,
                'effective_rate_bps' => $fxResult->effectiveRateBps,
                'certificate_hash' => $fxResult->certificateHash,
                'status' => IslamicTradeStatus::COMPLETED->value,
            ]);

            // Catat 3 leg transaksi tawarruq
            // Leg 1: Beli komoditas dari broker
            SybTawarruqTransaction::create([
                'order_id' => $order->id,
                'leg_number' => 1,
                'transaction_reference' => "LEG1-{$orderNumber}",
                'counterparty_name' => "{$exchangeName} Broker Member",
                'amount_cents' => $sourcePrincipalCents,
                'currency' => $sourceCurrency,
                'executed_at' => $now,
            ]);

            // Leg 2: Jual kredit ke nasabah
            SybTawarruqTransaction::create([
                'order_id' => $order->id,
                'leg_number' => 2,
                'transaction_reference' => "LEG2-{$orderNumber}",
                'counterparty_name' => $customerPartyRef,
                'amount_cents' => $fxResult->totalDeferredPriceCents,
                'currency' => $sourceCurrency,
                'executed_at' => $now->addSeconds(1),
            ]);

            // Leg 3: Nasabah tunjuk agen jual tunai di bursa pasar tujuan
            SybTawarruqTransaction::create([
                'order_id' => $order->id,
                'leg_number' => 3,
                'transaction_reference' => "LEG3-{$orderNumber}",
                'counterparty_name' => 'Global Trade Liquidator',
                'amount_cents' => $fxResult->targetReceivedCents,
                'currency' => $targetCurrency,
                'executed_at' => $now->addSeconds(2),
            ]);

            $commodityLot->update(['lot_status' => 'TRANSFERRED_LIQUIDATED']);

            // Posting ledger:
            // Debit syb:tawarruq_commodity_holding:IDR, Kredit syb:tawarruq_broker_payable:IDR
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_TAWARRUQ_BUY_LEG,
                reference: "twq:leg1:{$order->id}",
                description: "Pembelian lot komoditas bursa {$serialNumber} order {$orderNumber}",
                entries: [
                    ['account_code' => 'syb:tawarruq_commodity_holding:IDR', 'amount' => $sourcePrincipalCents],
                    ['account_code' => 'syb:tawarruq_broker_payable:IDR', 'amount' => -$sourcePrincipalCents],
                ]
            );

            // Debit syb:tawarruq_fx_clearing:IDR, Kredit syb:tawarruq_commodity_holding:IDR
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_TAWARRUQ_SELL_LEG,
                reference: "twq:leg2:{$order->id}",
                description: "Penyerahan komoditas tawarruq ke nasabah dan kliring valas {$orderNumber}",
                entries: [
                    ['account_code' => 'syb:tawarruq_fx_clearing:IDR', 'amount' => $sourcePrincipalCents],
                    ['account_code' => 'syb:tawarruq_commodity_holding:IDR', 'amount' => -$sourcePrincipalCents],
                ]
            );

            SybTradeAuditLog::create([
                'audit_ref' => $orderNumber,
                'event_name' => 'TAWARRUQ_FX_COMPLETED',
                'details' => "Tawarruq FX konversi {$sourceCurrency} {$sourcePrincipalCents} -> {$targetCurrency} {$fxResult->targetReceivedCents} kurs {$spotRateBps}",
                'shariah_compliance_status' => 'COMPLIANT',
            ]);

            return $order->load('commodityLot');
        });
    }
}
