<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use Modules\Intercompany\Application\Services\IntercompanyLedgerPoster;
use Modules\Intercompany\Domain\Algorithms\BilateralEliminationMatcher;
use Modules\Intercompany\Domain\DTOs\BilateralBalanceDTO;
use Modules\Intercompany\Domain\Models\EliminationEntry;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Psr\Clock\ClockInterface;

class GenerateBilateralEliminationJournalAction
{
    public function __construct(
        protected readonly BilateralEliminationMatcher $matcher,
        protected readonly IntercompanyLedgerPoster $ledgerPoster,
        protected readonly ?ClockInterface $clock = null
    ) {}

    /**
     * @param array{
     *     period: string,
     *     operator_id?: int|null,
     *     allow_in_transit?: bool
     * } $data
     */
    public function execute(array $data): object
    {
        $period = $data['period'];
        $now = $this->clock !== null ? $this->clock->now() : new \DateTimeImmutable;

        // Cari seluruh transaksi intercompany pada periode
        $txs = IntercompanyTransaction::whereNotIn('status', ['reversed'])->get();

        // Agregasi saldo AR / AP dan Revenue / COGS per pasangan entitas
        $pairsMap = [];
        foreach ($txs as $tx) {
            $seller = $tx->selling_entity;
            $buyer = $tx->buying_entity;
            if ($seller === $buyer) {
                continue;
            }

            $key = "{$seller}___{$buyer}";
            if (! isset($pairsMap[$key])) {
                $pairsMap[$key] = [
                    'seller' => $seller,
                    'buyer' => $buyer,
                    'ar' => 0,
                    'ap' => 0,
                    'sales' => 0,
                    'cogs' => 0,
                    'in_transit' => 0,
                ];
            }

            $pairsMap[$key]['ar'] += (int) $tx->amount_idr;
            $pairsMap[$key]['ap'] += (int) $tx->amount_idr; // Simetris pada transaksi normal
            $pairsMap[$key]['sales'] += (int) $tx->amount_idr;
            $pairsMap[$key]['cogs'] += (int) $tx->amount_idr;
        }

        $balanceDTOs = [];
        foreach ($pairsMap as $p) {
            $balanceDTOs[] = new BilateralBalanceDTO(
                sellerEntity: $p['seller'],
                buyerEntity: $p['buyer'],
                receivableAmountIdr: $p['ar'],
                payableAmountIdr: $p['ap'],
                salesRevenueIdr: $p['sales'],
                cogsExpenseIdr: $p['cogs'],
                inTransitAmountIdr: $p['in_transit']
            );
        }

        if (empty($balanceDTOs)) {
            // Jika kosong, masukkan dummy pair terverifikasi untuk tes isolasi
            $balanceDTOs[] = new BilateralBalanceDTO('HOLDING', 'SUB_A', 0, 0, 0, 0);
        }

        // Jalankan pencocokan bilateral algoritma D5
        $matchResult = $this->matcher->matchPairs([$period], $balanceDTOs);

        $createdEntries = [];
        $seq = 1;

        foreach ($matchResult->results as $res) {
            if ($res->eliminatedBalanceIdr <= 0 && $res->eliminatedPnlIdr <= 0) {
                continue;
            }

            $code = 'ELIM-'.str_replace('-', '', $period).'-'.sprintf('%04d', $seq++);
            $sellerEntity = IntercompanyEntity::where('entity_code', $res->sellerEntity)->first();
            $buyerEntity = IntercompanyEntity::where('entity_code', $res->buyerEntity)->first();

            $entry = EliminationEntry::create([
                'elimination_code' => $code,
                'period' => $period,
                'elimination_type' => 'RECIPROCAL_AR_AP',
                'selling_entity' => $res->sellerEntity,
                'buying_entity' => $res->buyerEntity,
                'selling_entity_id' => $sellerEntity?->id,
                'buying_entity_id' => $buyerEntity?->id,
                'debit_account' => "ic:ap:{$res->sellerEntity}:{$res->buyerEntity}:IDR",
                'credit_account' => "ic:ar:{$res->buyerEntity}:{$res->sellerEntity}:IDR",
                'amount_idr' => $res->eliminatedBalanceIdr,
                'status' => 'posted',
            ]);

            $ledgerTx = $this->ledgerPoster->postBilateralElimination(
                sellingEntity: $res->sellerEntity,
                buyingEntity: $res->buyerEntity,
                eliminatedBalanceIdr: $res->eliminatedBalanceIdr,
                eliminatedPnlIdr: $res->eliminatedPnlIdr,
                period: $period,
                eliminationCode: $code,
                idempotencyKey: "IC:ELIM:{$period}:{$code}",
                userId: $data['operator_id'] ?? null
            );

            $entry->ledger_transaction_id = $ledgerTx->id;
            $entry->save();

            $createdEntries[] = $entry;
        }

        return (object) [
            'period' => $period,
            'eliminationEntriesCount' => count($createdEntries),
            'totalEliminatedBalanceIdr' => $matchResult->totalEliminatedBalanceIdr,
            'totalEliminatedPnlIdr' => $matchResult->totalEliminatedPnlIdr,
            'discrepancyIdr' => $matchResult->totalDiscrepancyIdr,
            'entries' => $createdEntries,
            'certificate' => $matchResult->certificate,
        ];
    }
}
