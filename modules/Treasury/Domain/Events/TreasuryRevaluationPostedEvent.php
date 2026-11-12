<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Events;

use JsonSerializable;

/**
 * Event resmi diterbitkan saat kalkulasi revaluasi kurs akhir periode (PSAK 10)
 * berhasil diposting ke buku besar (EventCatalog: treasury.revaluation.posted.v1).
 */
final class TreasuryRevaluationPostedEvent implements JsonSerializable
{
    public const EVENT_NAME = 'treasury.revaluation.posted.v1';

    public function __construct(
        public readonly string $revaluationId,
        public readonly string $period,
        public readonly string $currency,
        public readonly int $foreignBalanceMinor,
        public readonly int $bookFunctionalIdr,
        public readonly int $revaluedFunctionalIdr,
        public readonly int $gainLossIdr,
        public readonly string $certificateHash,
        public readonly ?int $ledgerTransactionId = null,
        public readonly ?int $userId = null,
        public readonly string $entityCode = 'HOLDING',
        public readonly bool $isAsset = true
    ) {}

    /**
     * @return array<string, int|string|bool|null>
     */
    public function jsonSerialize(): array
    {
        return [
            'event' => self::EVENT_NAME,
            'revaluation_id' => $this->revaluationId,
            'period' => $this->period,
            'currency' => $this->currency,
            'foreign_balance_minor' => $this->foreignBalanceMinor,
            'book_functional_idr' => $this->bookFunctionalIdr,
            'revalued_functional_idr' => $this->revaluedFunctionalIdr,
            'gain_loss_idr' => $this->gainLossIdr,
            'certificate_hash' => $this->certificateHash,
            'ledger_transaction_id' => $this->ledgerTransactionId,
            'user_id' => $this->userId,
            'entity_code' => $this->entityCode,
            'is_asset' => $this->isAsset,
        ];
    }
}
