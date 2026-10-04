<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Contract\Domain\Models\Contract;

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
            $invoices = DB::table('mall_invoices')
                ->where('lease_id', $contract->linked_lease_id)
                ->where('status', '!=', 'cancelled')
                ->get(['id', 'total_amount', 'invoice_number']);

            $sources['lease_billing'] = $invoices->map(fn ($inv) => [
                (int) $inv->id,
                (int) $inv->total_amount,
                'Invoice sewa '.$inv->invoice_number,
            ])->all();
        }

        // 2. Waralaba Resto → royalty posting (29.6: kontrak waralaba).
        if ($contract->linked_royalty_ref !== null) {
            $postings = DB::table('resto_royalty_postings')
                ->where('contract_id', $contract->linked_royalty_ref)
                ->get(['id', 'royalty_amount', 'date']);

            $sources['royalty'] = $postings->map(fn ($p) => [
                (int) $p->id,
                (int) $p->royalty_amount,
                'Royalti '.(string) $p->date,
            ])->all();
        }

        // 3. Kontrak jasa pengiriman B2B → biaya leg pada carrier yang menjadi
        //    pihak lawan kontrak (29.6: kontrak pengiriman menautkan operasi lgx).
        if ($contract->contract_type->value === 'service') {
            $partyIds = DB::table('ctr_contract_parties')
                ->where('contract_id', $contract->id)
                ->where('role', 'second_party')
                ->pluck('party_id');

            $carrierIds = DB::table('lgx_carriers')->whereIn('party_id', $partyIds)->pluck('id');
            $legs = DB::table('lgx_shipment_legs')
                ->whereNotNull('cost_accrued_at')
                ->whereIn('carrier_id', $carrierIds)
                ->get(['id', 'carrier_cost_idr']);

            $sources['shipment'] = $legs->map(fn ($leg) => [
                (int) $leg->id,
                (int) $leg->carrier_cost_idr,
                'Biaya leg pengiriman #'.$leg->id,
            ])->all();
        }

        // 4. Kontrak penjualan jasa pengiriman kepada shipper → resi pascabayar
        //    milik lgx_shipper_accounts yang menjadi pihak lawan kontrak.
        if ($contract->contract_type->value === 'sale') {
            $partyIds = DB::table('ctr_contract_parties')
                ->where('contract_id', $contract->id)
                ->where('role', 'second_party')
                ->pluck('party_id');

            $shipperIds = DB::table('lgx_shipper_accounts')
                ->whereIn('party_id', $partyIds)
                ->pluck('shipper_id');

            $postpaidShipments = DB::table('lgx_shipments')
                ->whereIn('shipper_id', $shipperIds)
                ->where('payment_terms', 'postpaid')
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->get(['id', 'total_amount_idr', 'tracking_number']);

            $sources['sale'] = $postpaidShipments->map(fn ($sh) => [
                (int) $sh->id,
                (int) $sh->total_amount_idr,
                'Resi pascabayar '.$sh->tracking_number,
            ])->all();
        }

        // 5. Kontrak pembelian → PO supplier dengan party yang sama (29.5).
        if ($contract->contract_type->value === 'purchase') {
            $partyIds = DB::table('ctr_contract_parties')
                ->where('contract_id', $contract->id)
                ->where('role', 'second_party')
                ->pluck('party_id');

            $supplierIds = DB::table('resto_suppliers')
                ->whereIn('party_id', $partyIds)
                ->pluck('id');

            $purchaseOrders = DB::table('resto_purchase_orders')
                ->whereIn('supplier_id', $supplierIds)
                ->where('status', '!=', 'cancelled')
                ->get(['id', 'grand_total', 'number']);

            $sources['po'] = $purchaseOrders->map(fn ($po) => [
                (int) $po->id,
                (int) $po->grand_total,
                'PO '.$po->number,
            ])->all();
        }

        return $sources;
    }
}
