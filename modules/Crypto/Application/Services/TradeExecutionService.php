<?php

declare(strict_types=1);

namespace Modules\Crypto\Application\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Crypto\Domain\Enums\TradeSide;
use Modules\Crypto\Domain\Enums\TradeStatus;
use Modules\Crypto\Domain\Exceptions\InsufficientCryptoHoldingException;
use Modules\Crypto\Domain\Exceptions\QuoteExpiredException;
use Modules\Crypto\Domain\Models\CryptoQuote;
use Modules\Crypto\Domain\Models\CryptoTrade;

class TradeExecutionService
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly VerifiesWalletPin $verifyPinAction
    ) {}

    public function executeTrade(User $user, string $quoteUuid, string $pin): CryptoTrade
    {
        $quote = CryptoQuote::with('asset')
            ->where('uuid', $quoteUuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($quote->is_executed) {
            throw new \RuntimeException('Kuotasi harga ini sudah dieksekusi sebelumnya.');
        }

        if ($quote->isExpired()) {
            throw new QuoteExpiredException;
        }

        // Verify PIN
        $this->verifyPinAction->execute($user, $pin);

        $asset = $quote->asset;
        $symbol = $asset->symbol;

        // Ensure user accounts exist
        $userWalletIdr = $user->walletAccount('IDR');
        $userWalletCrypto = $user->walletAccount($symbol);

        $quantityBd = BigDecimal::of($quote->quantity);
        $grossBd = BigDecimal::of($quote->gross_idr);
        $feeBd = BigDecimal::of($quote->fee_idr);

        if ($quote->side === TradeSide::BUY) {
            $totalCostIdr = $grossBd->plus($feeBd);
            $userIdrBalance = BigDecimal::of($userWalletIdr->cached_balance ?: '0');

            if ($userIdrBalance->isLessThan($totalCostIdr)) {
                throw new InsufficientFundsException(
                    $userWalletIdr->code,
                    (string) $userIdrBalance,
                    (string) $totalCostIdr
                );
            }

            // 5 entries:
            // 1. User IDR: -(gross + fee)
            // 2. exchange:IDR: +gross
            // 3. fee:banking:IDR: +fee
            // 4. exchange:{ASSET}: -quantity
            // 5. User {ASSET}: +quantity
            $entries = [
                PostingEntryDTO::forAccount($userWalletIdr->id, 'IDR', $totalCostIdr->negated()),
                PostingEntryDTO::forCode('exchange:IDR', 'IDR', $grossBd),
                PostingEntryDTO::forCode('fee:banking:IDR', 'IDR', $feeBd),
                PostingEntryDTO::forCode("exchange:{$symbol}", $symbol, $quantityBd->negated()),
                PostingEntryDTO::forAccount($userWalletCrypto->id, $symbol, $quantityBd),
            ];

            $desc = "Beli {$quote->quantity} {$symbol} @ Rp ".number_format((float) $quote->price_idr, 0, ',', '.');
        } else {
            // SELL
            $userCryptoBalance = BigDecimal::of($userWalletCrypto->cached_balance ?: '0');

            if ($userCryptoBalance->isLessThan($quantityBd)) {
                throw new InsufficientCryptoHoldingException(
                    "Saldo {$symbol} tidak mencukupi untuk menjual {$quantityBd}. Saldo Anda: {$userCryptoBalance} {$symbol}"
                );
            }

            $netReceiveIdr = $grossBd->minus($feeBd);

            // 5 entries:
            // 1. User {ASSET}: -quantity
            // 2. exchange:{ASSET}: +quantity
            // 3. exchange:IDR: -gross
            // 4. fee:banking:IDR: +fee
            // 5. User IDR: +(gross - fee)
            $entries = [
                PostingEntryDTO::forAccount($userWalletCrypto->id, $symbol, $quantityBd->negated()),
                PostingEntryDTO::forCode("exchange:{$symbol}", $symbol, $quantityBd),
                PostingEntryDTO::forCode('exchange:IDR', 'IDR', $grossBd->negated()),
                PostingEntryDTO::forCode('fee:banking:IDR', 'IDR', $feeBd),
                PostingEntryDTO::forAccount($userWalletIdr->id, 'IDR', $netReceiveIdr),
            ];

            $desc = "Jual {$quote->quantity} {$symbol} @ Rp ".number_format((float) $quote->price_idr, 0, ',', '.');
        }

        $idempotencyKey = "crypto:trade:{$quote->uuid}";

        $tx = $this->ledger->post(new PostingDTO(
            type: TransactionType::EXCHANGE->value,
            description: $desc,
            idempotencyKey: $idempotencyKey,
            entries: $entries,
            meta: [
                'quote_uuid' => $quote->uuid,
                'symbol' => $symbol,
                'side' => $quote->side->value,
                'quantity' => $quote->quantity,
                'price_idr' => $quote->price_idr,
                'gross_idr' => $quote->gross_idr,
                'fee_idr' => $quote->fee_idr,
            ],
            postedAt: now(),
        ));

        $quote->update(['is_executed' => true]);

        return CryptoTrade::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'quote_id' => $quote->id,
            'side' => $quote->side,
            'quantity' => $quote->quantity,
            'price_idr' => $quote->price_idr,
            'gross_idr' => $quote->gross_idr,
            'fee_idr' => $quote->fee_idr,
            'ledger_transaction_id' => $tx->id,
            'status' => TradeStatus::COMPLETED,
            'created_at' => now(),
        ]);
    }
}
