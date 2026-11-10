<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Events;

use JsonSerializable;

/**
 * Event diterbitkan saat kalkulasi revaluasi kurs PSAK 10 berhasil diselesaikan & diposting.
 */
final class FxRevaluationCompletedEvent implements JsonSerializable
{
    public const EVENT_NAME = 'treasury.fx_revaluation.completed.v1';

    public function __construct(
        public string $revaluationId,
        public string $period,
        public string $currency,
        public int $foreignBalanceMinor,
        public int $bookFunctionalIdr,
        public int $revaluedFunctionalIdr,
        public int $gainLossIdr,
        public string $certificateHash,
        public ?int $userId = null
    ) {}

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
            'user_id' => $this->userId,
        ];
    }
}
