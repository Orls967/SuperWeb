<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\EsgMarketplaceListing;
use Modules\Egy\Domain\Models\EsgMarketplaceOrder;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;

/**
 * Memproses transaksi pembelian instrumen ESG pada marketplace terbuka konglomerasi.
 */
final class SettleEsgMarketplaceTradeAction
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    public function execute(
        int $listingId,
        int $buyerPartyId,
        int $purchaseQuantity,
        string $idempotencyKey,
    ): EsgMarketplaceOrder {
        if ($purchaseQuantity <= 0) {
            throw new InvalidArgumentException('Kuantitas pembelian harus > 0');
        }

        $listing = EsgMarketplaceListing::findOrFail($listingId);

        if ($listing->available_quantity < $purchaseQuantity) {
            throw new InvalidArgumentException(
                "Stok inventori listing {$listing->listing_code} tidak mencukupi (tersedia: {$listing->available_quantity})"
            );
        }

        $unitPriceMinor = (int) $listing->price_minor_per_unit;
        $totalAmountIdr = (int) ($purchaseQuantity * $unitPriceMinor);

        $orderNumber = sprintf('ESG-ORD-%d-%s', $listing->id, substr(hash('sha256', $idempotencyKey), 0, 10));

        $existing = EsgMarketplaceOrder::where('order_number', $orderNumber)->first();
        if ($existing !== null) {
            return $existing;
        }

        // Pembukuan ledger double-entry
        $buyerAccount = EgyAccounts::customerReceivable((string) $buyerPartyId);
        $marketplaceRevenueAccount = 'egy:esg_marketplace_revenue:IDR';

        $postingLines = Posting::lines()
            ->debit($buyerAccount->code, $totalAmountIdr)
            ->credit($marketplaceRevenueAccount, $totalAmountIdr);

        $tx = $this->ledgerService->post(new PostingDTO(
            type: EgyTransactionTypes::EGY_GRID_BILLING->value,
            description: sprintf('Pembelian ESG Marketplace %s (%d unit)', $orderNumber, $purchaseQuantity),
            idempotencyKey: $idempotencyKey,
            entries: $postingLines->toEntries('IDR'),
            referenceType: 'ESG_MARKETPLACE_ORDER',
            referenceId: $orderNumber,
        ));

        // Kurangi stok listing
        $listing->available_quantity -= $purchaseQuantity;
        $listing->sold_quantity += $purchaseQuantity;
        $listing->save();

        $rawSignature = sprintf(
            'ESG-ORDER|ORD:%s|LST:%d|BUYER:%d|QTY:%d|VAL:%d|TX:%d',
            $orderNumber,
            $listing->id,
            $buyerPartyId,
            $purchaseQuantity,
            $totalAmountIdr,
            $tx->id
        );
        $certificateHash = hash('sha256', $rawSignature);

        return EsgMarketplaceOrder::create([
            'order_number' => $orderNumber,
            'listing_id' => $listing->id,
            'buyer_party_id' => $buyerPartyId,
            'purchased_quantity' => $purchaseQuantity,
            'unit_price_minor' => $unitPriceMinor,
            'total_amount_idr' => $totalAmountIdr,
            'ledger_transaction_id' => $tx->id,
            'redemption_code' => sprintf('RDM-%s', strtoupper(substr(hash('sha256', $orderNumber), 0, 8))),
            'claim_status' => 'fulfilled',
            'certificate_hash' => $certificateHash,
        ]);
    }
}
