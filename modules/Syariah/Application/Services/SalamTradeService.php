<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Islamic\SalamPositionBook;
use Modules\Shared\Domain\Algorithms\Islamic\SalamPositionBookCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Islamic\SalamPositionBookResult;
use Modules\Syariah\Domain\Enums\IslamicTradeContractType;
use Modules\Syariah\Domain\Enums\IslamicTradeStatus;
use Modules\Syariah\Domain\Models\SybParallelSalamHedge;
use Modules\Syariah\Domain\Models\SybSalamContract;
use Modules\Syariah\Domain\Models\SybSalamDelivery;
use Modules\Syariah\Domain\Models\SybTradeAuditLog;
use Modules\Syariah\Ledger\ShariahLedgerPoster;
use Modules\Syariah\Ledger\SyariahAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

final class SalamTradeService
{
    public function __construct(
        private readonly ShariahLedgerPoster $ledgerPoster,
        private readonly SalamPositionBook $positionBookAlgorithm,
        private readonly SalamPositionBookCertificateVerifier $certificateVerifier,
    ) {}

    public function contractSalam(
        string $sellerPartyRef,
        string $buyerPartyRef,
        string $commodityCode,
        string $commodityGrade,
        string $quantityUnit,
        int $quantityOrdered,
        int $advancePaymentCents,
        CarbonImmutable $promisedDeliveryDate,
        string $deliveryWarehouseCode
    ): SybSalamContract {
        if ($quantityOrdered <= 0 || $advancePaymentCents <= 0) {
            throw new InvalidArgumentException('Kuantum dan pembayaran di muka salam harus positif.');
        }

        SyariahAccounts::ensureAccountsExist();

        return DB::transaction(function () use (
            $sellerPartyRef,
            $buyerPartyRef,
            $commodityCode,
            $commodityGrade,
            $quantityUnit,
            $quantityOrdered,
            $advancePaymentCents,
            $promisedDeliveryDate,
            $deliveryWarehouseCode
        ) {
            $count = SybSalamContract::count() + 1;
            $contractNumber = sprintf('SLM-%s-%04d', $promisedDeliveryDate->format('Ym'), $count);

            $salam = SybSalamContract::create([
                'contract_number' => $contractNumber,
                'seller_party_ref' => $sellerPartyRef,
                'buyer_party_ref' => $buyerPartyRef,
                'commodity_code' => $commodityCode,
                'commodity_grade' => $commodityGrade,
                'quantity_unit' => $quantityUnit,
                'quantity_ordered' => $quantityOrdered,
                'quantity_delivered' => 0,
                'advance_payment_cents' => $advancePaymentCents,
                'promised_delivery_date' => $promisedDeliveryDate->toDateString(),
                'delivery_warehouse_code' => $deliveryWarehouseCode,
                'status' => IslamicTradeStatus::ACTIVE->value,
            ]);

            // Posting advance payment tunai penuh di muka:
            // Debit syb:salam_financing_advance:IDR, Kredit syb:cash_pool:IDR
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_SALAM_ADVANCE_PAY,
                reference: "salam:advance:{$salam->id}",
                description: "Pembayaran advance tunai penuh di muka kontrak salam {$contractNumber}",
                entries: [
                    ['account_code' => 'syb:salam_financing_advance:IDR', 'amount' => $advancePaymentCents],
                    ['account_code' => 'syb:cash_pool:IDR', 'amount' => -$advancePaymentCents],
                ]
            );

            SybTradeAuditLog::create([
                'audit_ref' => $contractNumber,
                'event_name' => 'SALAM_CONTRACTED',
                'details' => "Kontrak Salam {$commodityCode} kuantum {$quantityOrdered} {$quantityUnit} advance IDR {$advancePaymentCents}",
                'shariah_compliance_status' => 'COMPLIANT',
            ]);

            return $salam;
        });
    }

    public function contractParallelHedge(
        int $salamId,
        string $offtakerPartyRef,
        int $agreedSalePriceCents,
        int $quantityHedged,
        CarbonImmutable $deliveryDate
    ): SybParallelSalamHedge {
        $salam = SybSalamContract::findOrFail($salamId);

        if ($quantityHedged <= 0 || $agreedSalePriceCents <= 0) {
            throw new InvalidArgumentException('Kuantum dan harga jual kesepakatan hedge harus positif.');
        }

        if ($quantityHedged > $salam->quantity_ordered) {
            throw new InvalidArgumentException('Larangan short selling: kuantum parallel salam tidak boleh melebihi salam pokok.');
        }

        return DB::transaction(function () use (
            $salam,
            $offtakerPartyRef,
            $agreedSalePriceCents,
            $quantityHedged,
            $deliveryDate
        ) {
            $count = SybParallelSalamHedge::count() + 1;
            $hedgeNumber = sprintf('HDG-SLM-%s-%04d', $deliveryDate->format('Ym'), $count);

            $hedge = SybParallelSalamHedge::create([
                'salam_id' => $salam->id,
                'hedge_contract_number' => $hedgeNumber,
                'offtaker_party_ref' => $offtakerPartyRef,
                'agreed_sale_price_cents' => $agreedSalePriceCents,
                'quantity_hedged' => $quantityHedged,
                'delivery_date' => $deliveryDate->toDateString(),
                'status' => IslamicTradeStatus::ACTIVE->value,
            ]);

            SybTradeAuditLog::create([
                'audit_ref' => $hedgeNumber,
                'event_name' => 'PARALLEL_SALAM_HEDGED',
                'details' => "Parallel Salam off-taker {$offtakerPartyRef} kuantum {$quantityHedged} harga IDR {$agreedSalePriceCents}",
                'shariah_compliance_status' => 'COMPLIANT',
            ]);

            return $hedge;
        });
    }

    public function evaluatePositionBook(string $commodityCode, int $spotPricePerUnitCents): SalamPositionBookResult
    {
        $salamContracts = SybSalamContract::where('commodity_code', $commodityCode)
            ->where('status', IslamicTradeStatus::ACTIVE->value)
            ->get()
            ->map(fn (SybSalamContract $c) => [
                'contract_id' => (string) $c->id,
                'commodity_code' => $c->commodity_code,
                'grade' => $c->commodity_grade,
                'quantity_ordered' => (int) $c->quantity_ordered,
                'quantity_delivered' => (int) $c->quantity_delivered,
                'advance_payment_cents' => (int) $c->advance_payment_cents,
                'promised_delivery_date' => (string) $c->promised_delivery_date,
            ])
            ->all();

        $parallelHedges = SybParallelSalamHedge::whereHas('salamContract', function ($q) use ($commodityCode) {
            $q->where('commodity_code', $commodityCode);
        })
            ->where('status', IslamicTradeStatus::ACTIVE->value)
            ->get()
            ->map(fn (SybParallelSalamHedge $h) => [
                'hedge_id' => (string) $h->id,
                'commodity_code' => $commodityCode,
                'quantity_hedged' => (int) $h->quantity_hedged,
                'agreed_sale_price_cents' => (int) $h->agreed_sale_price_cents,
                'delivery_date' => (string) $h->delivery_date,
            ])
            ->all();

        $result = $this->positionBookAlgorithm->evaluate(
            commodityCode: $commodityCode,
            salamContracts: $salamContracts,
            parallelHedges: $parallelHedges,
            spotPricePerUnitCents: $spotPricePerUnitCents
        );

        $this->certificateVerifier->verify($result);

        return $result;
    }

    public function recordDelivery(
        int $salamId,
        int $quantityReceived,
        string $warehouseReceiptNumber,
        string $inspectionStatus = 'ACCEPTED_GRADE'
    ): SybSalamDelivery {
        $salam = SybSalamContract::findOrFail($salamId);

        if ($quantityReceived <= 0) {
            throw new InvalidArgumentException('Kuantum komoditas yang diserahkan harus positif.');
        }

        SyariahAccounts::ensureAccountsExist();

        return DB::transaction(function () use (
            $salam,
            $quantityReceived,
            $warehouseReceiptNumber,
            $inspectionStatus
        ) {
            $count = SybSalamDelivery::count() + 1;
            $batchRef = sprintf('DLV-SLM-%04d', $count);

            $delivery = SybSalamDelivery::create([
                'salam_id' => $salam->id,
                'delivery_batch_ref' => $batchRef,
                'quantity_received' => $quantityReceived,
                'warehouse_receipt_number' => $warehouseReceiptNumber,
                'inspection_status' => $inspectionStatus,
                'delivered_at' => CarbonImmutable::now(),
            ]);

            $newDelivered = $salam->quantity_delivered + $quantityReceived;
            $salam->update([
                'quantity_delivered' => $newDelivered,
                'status' => $newDelivered >= $salam->quantity_ordered
                    ? IslamicTradeStatus::COMPLETED->value
                    : IslamicTradeStatus::IN_DELIVERY->value,
            ]);

            // Ledger posting: Reklasifikasi piutang advance ke persediaan komoditas riil
            $portionCost = (int) intdiv($salam->advance_payment_cents * $quantityReceived, $salam->quantity_ordered);
            if ($portionCost > 0) {
                $this->ledgerPoster->postSimple(
                    type: SyariahTransactionTypes::SYB_SALAM_DELIVERY,
                    reference: "salam:delivery:{$delivery->id}",
                    description: "Penerimaan fisik komoditas salam batch {$batchRef} di gudang",
                    entries: [
                        ['account_code' => 'syb:salam_inventory:IDR', 'amount' => $portionCost],
                        ['account_code' => 'syb:salam_financing_advance:IDR', 'amount' => -$portionCost],
                    ]
                );
            }

            return $delivery;
        });
    }
}
