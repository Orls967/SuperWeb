<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\Microgrid;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;

/**
 * Aksi Penyelesaian Perdagangan Energi P2P Antar-Prosumer di Microgrid Lokal.
 * Memfasilitasi transaksi listrik antar penyewa rooftop solar (prosumer) dan konsumen
 * dengan pembebanan tarif sewa jaringan distribusi lokal (LNUOS wheeling charge).
 */
final class SettlePeerToPeerEnergyTradeAction
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * @param  int  $microgridId  ID microgrid lokal
     * @param  string  $sellerPartyId  ID prosumer penjual listrik
     * @param  string  $buyerPartyId  ID konsumen pembeli listrik
     * @param  int  $tradedEnergyKwh  Volume listrik yang diperdagangkan (kWh)
     * @param  int  $unitPriceIdrPerKwh  Harga sepakat (IDR per kWh)
     * @param  int  $wheelingRateBps  Tarif sewa jaringan microgrid (misal 500 bps = 5%)
     */
    public function execute(
        int $microgridId,
        string $sellerPartyId,
        string $buyerPartyId,
        int $tradedEnergyKwh,
        int $unitPriceIdrPerKwh,
        int $wheelingRateBps = 500,
        string $idempotencyKey = ''
    ): array {
        if ($tradedEnergyKwh <= 0 || $unitPriceIdrPerKwh <= 0) {
            throw new InvalidArgumentException('Volume energi dan harga satuan P2P harus bernilai > 0.');
        }

        $microgrid = Microgrid::findOrFail($microgridId);

        $grossTradeAmountIdr = $tradedEnergyKwh * $unitPriceIdrPerKwh;
        $wheelingFeeIdr = (int) intdiv($grossTradeAmountIdr * $wheelingRateBps, 10000);
        $netSellerEarningsIdr = $grossTradeAmountIdr - $wheelingFeeIdr;

        $buyerAccount = EgyAccounts::customerReceivable($buyerPartyId);
        $sellerRevenueAccount = 'egy:grid_electricity_revenue:IDR';
        $wheelingRevenueAccount = 'egy:utility_sales_revenue:IDR';

        $lines = Posting::lines()
            ->debit($buyerAccount->code, $grossTradeAmountIdr)
            ->credit($sellerRevenueAccount, $netSellerEarningsIdr)
            ->credit($wheelingRevenueAccount, $wheelingFeeIdr);

        $tradeCode = sprintf('P2P-%d-%s-%s-%s', $microgridId, $sellerPartyId, $buyerPartyId, now()->format('YmdHi'));
        $idempKey = ! empty($idempotencyKey) ? $idempotencyKey : "P2P-TRADE:{$tradeCode}";

        $tx = $this->ledgerService->post(new PostingDTO(
            type: EgyTransactionTypes::EGY_GRID_BILLING->value,
            description: sprintf('Penyelesaian P2P Energi Microgrid %s: %d kWh', $microgrid->microgrid_code, $tradedEnergyKwh),
            idempotencyKey: $idempKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'P2P_ENERGY_TRADE',
            referenceId: $tradeCode,
        ));

        return [
            'trade_code' => $tradeCode,
            'microgrid_code' => $microgrid->microgrid_code,
            'seller_party_id' => $sellerPartyId,
            'buyer_party_id' => $buyerPartyId,
            'traded_energy_kwh' => $tradedEnergyKwh,
            'unit_price_idr' => $unitPriceIdrPerKwh,
            'gross_amount_idr' => $grossTradeAmountIdr,
            'wheeling_fee_idr' => $wheelingFeeIdr,
            'net_seller_idr' => $netSellerEarningsIdr,
            'ledger_transaction_id' => $tx->id,
        ];
    }
}
