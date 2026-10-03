<?php

declare(strict_types=1);

namespace Modules\Banking\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Exceptions\SelfTransferException;
use Modules\Banking\Domain\Models\LedgerTransaction;
use RuntimeException;

class TransferAction
{
    public const FEE_THRESHOLD = 1_000_000;

    public const ADMIN_FEE = 2_500;

    public function __construct(
        protected Ledger $ledger,
        protected VerifyPinAction $verifyPin,
    ) {}

    public static function maskName(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));
        $maskedWords = [];

        foreach ($words as $word) {
            $len = mb_strlen($word);
            if ($len <= 2) {
                $maskedWords[] = mb_substr($word, 0, 1).'*';
            } else {
                $prefix = mb_substr($word, 0, 2);
                $maskLen = max(3, $len - 2);
                $maskedWords[] = $prefix.str_repeat('*', $maskLen);
            }
        }

        return implode(' ', $maskedWords);
    }

    public function execute(
        User $sender,
        User $recipient,
        BigDecimal|string|int|float $amount,
        string $pin,
        ?string $note = null,
        ?string $idempotencyKey = null,
    ): LedgerTransaction {
        // Self-transfer check
        if ($sender->id === $recipient->id) {
            throw new SelfTransferException;
        }

        // Rate limit: 10 per minute per user
        $rateLimitKey = 'transfer:user:'.$sender->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            throw new RuntimeException("Terlalu banyak permintaan transfer. Silakan tunggu {$seconds} detik.");
        }

        // Verify PIN first
        $this->verifyPin->execute($sender, $pin);

        // Record rate limit attempt upon successful PIN
        RateLimiter::hit($rateLimitKey, 60);

        $amountBd = $amount instanceof BigDecimal ? $amount : BigDecimal::of((string) $amount);
        if ($amountBd->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new InvalidArgumentException('Nominal transfer harus lebih besar dari 0.');
        }

        // Calculate fee
        $feeBd = BigDecimal::zero();
        if ($amountBd->isGreaterThan(BigDecimal::of(self::FEE_THRESHOLD))) {
            $feeBd = BigDecimal::of(self::ADMIN_FEE);
        }

        $totalDeduction = $amountBd->plus($feeBd);

        return DB::transaction(function () use ($sender, $recipient, $amountBd, $feeBd, $totalDeduction, $note, $idempotencyKey): LedgerTransaction {
            $senderWallet = $sender->walletAccount('IDR');
            $recipientWallet = $recipient->walletAccount('IDR');

            // A transfer has no natural source identifier. Without a caller key,
            // preserve distinct transfers rather than deduping them incorrectly.
            $key = $idempotencyKey ?? ('xfer_'.$sender->id.'_'.Str::random(16));

            $entries = [
                PostingEntryDTO::forAccount($senderWallet->id, 'IDR', $totalDeduction->negated()),
                PostingEntryDTO::forAccount($recipientWallet->id, 'IDR', $amountBd),
            ];

            if ($feeBd->isPositive()) {
                $entries[] = PostingEntryDTO::forCode('fee:banking:IDR', 'IDR', $feeBd);
            }

            $desc = "Transfer ke {$recipient->name}".($note ? " - {$note}" : '');

            $dto = new PostingDTO(
                type: TransactionType::TRANSFER->value,
                description: $desc,
                idempotencyKey: $key,
                entries: $entries,
                referenceType: User::class,
                referenceId: $recipient->id,
                meta: [
                    'sender_id' => $sender->id,
                    'recipient_id' => $recipient->id,
                    'amount' => $amountBd->__toString(),
                    'fee' => $feeBd->__toString(),
                    'note' => $note,
                ],
                createdBy: $sender->id,
                postedAt: now(),
            );

            return $this->ledger->post($dto);
        });
    }
}
