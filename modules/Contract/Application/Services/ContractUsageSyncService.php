<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Contract\Domain\Models\Contract;
use Modules\Logistics\Contracts\LogisticsUsageReaderInterface;
use Modules\Mall\Contracts\MallUsageReaderInterface;
use Modules\Resto\Contracts\RestoUsageReaderInterface;

/**
 * Sinkronisasi pemakaian plafon kontrak dari transaksi riil (29.5 & 29.6).
 *
 * Semua sumber diakses lewat query tabel (bukan import Domain modul lain)
 * sehingga arsitektur batas modul tetap terjaga. Idempoten: baris
 * `ctr_usage_ledger` unik per (source_type, source_id).
 */
class ContractUsageSyncService
{
    public function __construct(
        private readonly ContractUsageService $usage,
    ) {}

    /**
     * Sinkronkan seluruh kontrak aktif dari seluruh sumber.
     *
     * @return array<string, int> [source_type => jumlah baris tercatat]
     */
    public function syncAll(): array
    {
        $contracts = Contract::query()
            ->whereIn('status', ['active', 'signed', 'suspended'])
            ->get();

        $recorded = [];

        foreach ($contracts as $contract) {
            foreach ($this->sourcesFor($contract) as $type => $rows) {
                foreach ($rows as [$sourceId, $amount, $note]) {
                    if ($this->usage->recordUsage($contract, $type, $sourceId, $amount, $note)) {
                        $recorded[$type] = ($recorded[$type] ?? 0) + 1;
                    }
                }
            }
        }

        return $recorded;
    }

    /**
     * Ambil baris transaksi riil untuk satu kontrak.
     *
     * @return array<string, array<int, array{0: int, 1: int, 2: string}>>
     */
    private function sourcesFor(Contract $contract): array
    {
        $sources = [];

        // 1. Sewa Mall → invoice lease (29.6: kontrak sewa menautkan lease).
        if ($contract->linked_lease_id !== null) {
            $invoices = app(MallUsageReaderInterface::class)
                ->getInvoicesForLease((int) $contract->linked_lease_id);

            $sources['lease_billing'] = array_map(fn ($inv) => [
                (int) $inv['id'],
                (int) $inv['total_amount'],
                'Invoice sewa '.$inv['invoice_number'],
            ], $invoices);
        }

        // 2. Waralaba Resto → royalty posting (29.6: kontrak waralaba).
        if ($contract->linked_royalty_ref !== null) {
            $postings = app(RestoUsageReaderInterface::class)
                ->getRoyaltyPostingsForContract((string) $contract->linked_royalty_ref);

            $sources['royalty'] = array_map(fn ($p) => [
                (int) $p['id'],
                (int) $p['royalty_amount'],
                'Royalti '.(string) $p['date'],
            ], $postings);
        }

        // 3. Kontrak jasa pengiriman B2B → biaya leg pada carrier yang menjadi
        //    pihak lawan kontrak (29.6: kontrak pengiriman menautkan operasi lgx).
        if ($contract->contract_type->value === 'service') {
            $partyIds = DB::table('ctr_contract_parties')
                ->where('contract_id', $contract->id)
                ->where('role', 'second_party')
                ->pluck('party_id')
                ->all();

            $legs = app(LogisticsUsageReaderInterface::class)
                ->getCarrierLegsForParties($partyIds);

            $sources['shipment'] = array_map(fn ($leg) => [
                (int) $leg['id'],
                (int) $leg['cost_idr'],
                (string) $leg['label'],
            ], $legs);
        }

        // 4. Kontrak penjualan jasa pengiriman kepada shipper → resi pascabayar
        //    milik lgx_shipper_accounts yang menjadi pihak lawan kontrak.
        if ($contract->contract_type->value === 'sale') {
            $partyIds = DB::table('ctr_contract_parties')
                ->where('contract_id', $contract->id)
                ->where('role', 'second_party')
                ->pluck('party_id')
                ->all();

            $postpaidShipments = app(LogisticsUsageReaderInterface::class)
                ->getPostpaidShipmentsForParties($partyIds);

            $sources['sale'] = array_map(fn ($sh) => [
                (int) $sh['id'],
                (int) $sh['amount'],
                (string) $sh['label'],
            ], $postpaidShipments);
        }

        // 5. Kontrak pembelian → PO supplier dengan party yang sama (29.5).
        if ($contract->contract_type->value === 'purchase') {
            $partyIds = DB::table('ctr_contract_parties')
                ->where('contract_id', $contract->id)
                ->where('role', 'second_party')
                ->pluck('party_id')
                ->all();

            $purchaseOrders = app(RestoUsageReaderInterface::class)
                ->getPurchaseOrdersForSupplierParties($partyIds);

            $sources['po'] = array_map(fn ($po) => [
                (int) $po['id'],
                (int) $po['amount'],
                (string) $po['label'],
            ], $purchaseOrders);
        }

        return $sources;
    }
}
