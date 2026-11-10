<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Intercompany\Domain\Enums\IntercompanyTransactionStatus;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\StateMachines\IntercompanyTransactionStateMachine;
use Psr\Clock\ClockInterface;

/**
 * Layanan Resolusi Sengketa Cut-Off & Selisih Bilateral Intercompany (52.1, 52.4).
 * Menangani rekonsiliasi perbedaan tanggal cut-off faktur, sengketa kurs mata uang asing,
 * barang dalam perjalanan (goods in-transit), dan penerbitan nota penyesuaian (adjustment notes).
 */
class IntercompanyCutoffDisputeService
{
    public function __construct(
        protected readonly IntercompanyLedgerPoster $ledgerPoster,
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Menginvestigasi perbedaan saldo bilateral antara dua entitas legal grup.
     *
     * @return array{
     *     seller_entity: string,
     *     buyer_entity: string,
     *     period: string,
     *     total_seller_ar_minor: int,
     *     total_buyer_ap_minor: int,
     *     net_discrepancy_minor: int,
     *     in_transit_txs_count: int,
     *     disputed_txs_count: int,
     *     in_transit_items: list<array<string, mixed>>,
     *     recommended_action: string
     * }
     */
    public function investigateDiscrepancy(
        string $sellerEntity,
        string $buyerEntity,
        string $period
    ): array {
        $txs = IntercompanyTransaction::where('selling_entity', $sellerEntity)
            ->where('buying_entity', $buyerEntity)
            ->get();

        $sellerAr = 0;
        $buyerAp = 0;
        $inTransitItems = [];
        $disputedCount = 0;

        foreach ($txs as $tx) {
            $amount = (int) $tx->amount_idr;

            if (in_array($tx->status, ['posted_seller', 'posted_buyer', 'reconciled'], true)) {
                $sellerAr += $amount;
            }

            if (in_array($tx->status, ['posted_buyer', 'reconciled'], true)) {
                $buyerAp += $amount;
            }

            if ($tx->status === IntercompanyTransactionStatus::POSTED_SELLER->value) {
                $inTransitItems[] = [
                    'transaction_code' => $tx->transaction_code,
                    'amount_idr' => $amount,
                    'invoice_ref' => $tx->sales_invoice_ref,
                    'created_at' => $tx->created_at?->toIso8601String(),
                    'reason' => 'Faktur penjual terbit, belum diakui di buku pembeli (Goods In-Transit)',
                ];
            }

            if ($tx->status === IntercompanyTransactionStatus::DISPUTED->value) {
                $disputedCount++;
            }
        }

        $discrepancy = $sellerAr - $buyerAp;

        $recommended = 'NONE';
        if ($discrepancy === 0) {
            $recommended = 'READY_TO_ELIMINATE';
        } elseif (count($inTransitItems) > 0 && abs($discrepancy - array_sum(array_column($inTransitItems, 'amount_idr'))) === 0) {
            $recommended = 'POST_BUYER_IN_TRANSIT_ACCRUAL';
        } else {
            $recommended = 'ESCALATE_TO_GROUP_CONTROLLER';
        }

        return [
            'seller_entity' => $sellerEntity,
            'buyer_entity' => $buyerEntity,
            'period' => $period,
            'total_seller_ar_minor' => $sellerAr,
            'total_buyer_ap_minor' => $buyerAp,
            'net_discrepancy_minor' => $discrepancy,
            'in_transit_txs_count' => count($inTransitItems),
            'disputed_txs_count' => $disputedCount,
            'in_transit_items' => $inTransitItems,
            'recommended_action' => $recommended,
        ];
    }

    /**
     * Menyelesaikan transaksi gantung (in-transit) dengan memosting pengakuan di sisi pembeli.
     */
    public function resolveInTransit(
        int $transactionId,
        int $operatorId,
        string $resolutionNotes
    ): IntercompanyTransaction {
        $tx = IntercompanyTransaction::findOrFail($transactionId);

        if ($tx->status !== IntercompanyTransactionStatus::POSTED_SELLER->value) {
            throw new InvalidArgumentException("Transaksi {$tx->transaction_code} bukan berstatus posted_seller (in-transit).");
        }

        $sm = new IntercompanyTransactionStateMachine($tx);
        $sm->transitionTo(
            targetStatus: IntercompanyTransactionStatus::POSTED_BUYER,
            operatorId: $operatorId,
            reason: $resolutionNotes
        );

        $tx->refresh();

        return $tx;
    }

    /**
     * Membuka tiket sengketa transaksi bilateral karena ketidaksesuaian nominal atau kuantitas barang.
     */
    public function raiseDispute(
        int $transactionId,
        int $operatorId,
        string $disputeReason
    ): IntercompanyTransaction {
        $tx = IntercompanyTransaction::findOrFail($transactionId);

        $sm = new IntercompanyTransactionStateMachine($tx);
        $sm->transitionTo(
            targetStatus: IntercompanyTransactionStatus::DISPUTED,
            operatorId: $operatorId,
            reason: $disputeReason
        );

        $tx->refresh();

        return $tx;
    }
}
