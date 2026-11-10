<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Events;

use JsonSerializable;

/**
 * Event diterbitkan saat sesi rekonsiliasi mutasi bank selesai dijalankan.
 */
final class BankStatementReconciledEvent implements JsonSerializable
{
    public const EVENT_NAME = 'treasury.bank_reconciliation.completed.v1';

    public function __construct(
        public string $bankAccountId,
        public string $accountNumber,
        public string $currency,
        public int $matchedCount,
        public int $unmatchedCount,
        public int $matchedAmountMinor,
        public int $unmatchedAmountMinor,
        public string $certificateHash,
        public ?int $actorId = null
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'event' => self::EVENT_NAME,
            'bank_account_id' => $this->bankAccountId,
            'account_number' => $this->accountNumber,
            'currency' => $this->currency,
            'matched_count' => $this->matchedCount,
            'unmatched_count' => $this->unmatchedCount,
            'matched_amount_minor' => $this->matchedAmountMinor,
            'unmatched_amount_minor' => $this->unmatchedAmountMinor,
            'certificate_hash' => $this->certificateHash,
            'actor_id' => $this->actorId,
        ];
    }
}
