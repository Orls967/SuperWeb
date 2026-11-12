<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final class BudgetCommitmentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $commitmentCode,
        public readonly int $amountMinor,
        public readonly string $status,
        public readonly ?string $errorMessage = null,
        public readonly int $remainingAvailableMinor = 0
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public static function successful(
        string $commitmentCode,
        int $amountMinor,
        string $status,
        int $remainingAvailableMinor = 0
    ): self {
        return new self(
            success: true,
            commitmentCode: $commitmentCode,
            amountMinor: $amountMinor,
            status: $status,
            errorMessage: null,
            remainingAvailableMinor: $remainingAvailableMinor
        );
    }

    public static function failed(string $commitmentCode, string $errorMessage): self
    {
        return new self(
            success: false,
            commitmentCode: $commitmentCode,
            amountMinor: 0,
            status: 'FAILED',
            errorMessage: $errorMessage,
            remainingAvailableMinor: 0
        );
    }
}
