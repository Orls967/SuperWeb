<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Values;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable value object representing a modular credit exposure tranche (W8-D57).
 *
 * Models financial commitments across business lines (Procurement, Logistics, Trade Finance)
 * against a group vendor cluster.
 */
final class VendorExposureTranche
{
    public const MATURITY_CURRENT = 'CURRENT'; // < 30 days

    public const MATURITY_SHORT = 'SHORT_TERM'; // 30 - 90 days

    public const MATURITY_MEDIUM = 'MEDIUM_TERM'; // 90 - 180 days

    public const MATURITY_LONG = 'LONG_TERM'; // > 180 days

    /**
     * @param  string  $sourceModule  Originating module (Procurement, Logistics, TradeFinance, etc.)
     * @param  string  $trancheReference  Unique business reference (PO-12345, LC-9876, INV-443)
     * @param  int  $amountIdr  Financial exposure magnitude in integer IDR
     * @param  string  $status  Current transaction lifecycle status
     * @param  string|null  $counterpartyName  Name of the specific entity within cluster
     * @param  DateTimeImmutable|null  $dueDate  Maturity or commitment expiration date
     * @param  bool  $isContingentLiability  Whether exposure is contingent (e.g. Bank Guarantee, LC)
     * @param  float  $riskWeight  Haircut or risk weight multiplier (0.0 to 1.0)
     */
    public function __construct(
        public readonly string $sourceModule,
        public readonly string $trancheReference,
        public readonly int $amountIdr,
        public readonly string $status = 'COMMITTED',
        public readonly ?string $counterpartyName = null,
        public readonly ?DateTimeImmutable $dueDate = null,
        public readonly bool $isContingentLiability = false,
        public readonly float $riskWeight = 1.0
    ) {
        if ($amountIdr < 0) {
            throw new InvalidArgumentException("Amount IDR cannot be negative, got: {$amountIdr}");
        }

        if ($riskWeight < 0.0 || $riskWeight > 1.0) {
            throw new InvalidArgumentException("Risk weight must be between 0.0 and 1.0, got: {$riskWeight}");
        }
    }

    /**
     * Calculate risk-adjusted exposure in IDR.
     */
    public function getRiskAdjustedAmountIdr(): int
    {
        return (int) round($this->amountIdr * $this->riskWeight);
    }

    /**
     * Determine maturity bucket based on days to due date.
     */
    public function getMaturityBucket(?DateTimeImmutable $relativeTo = null): string
    {
        if ($this->dueDate === null) {
            return self::MATURITY_CURRENT;
        }

        $now = $relativeTo ?? new DateTimeImmutable;
        $days = (int) $now->diff($this->dueDate)->format('%r%a');

        return match (true) {
            $days <= 30 => self::MATURITY_CURRENT,
            $days <= 90 => self::MATURITY_SHORT,
            $days <= 180 => self::MATURITY_MEDIUM,
            default => self::MATURITY_LONG,
        };
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'module' => $this->sourceModule,
            'reference' => $this->trancheReference,
            'amount_idr' => $this->amountIdr,
            'risk_adjusted_amount_idr' => $this->getRiskAdjustedAmountIdr(),
            'status' => $this->status,
            'counterparty' => $this->counterpartyName,
            'due_date' => $this->dueDate?->format('Y-m-d'),
            'is_contingent' => $this->isContingentLiability,
            'risk_weight' => $this->riskWeight,
            'maturity_bucket' => $this->getMaturityBucket(),
        ];
    }
}
