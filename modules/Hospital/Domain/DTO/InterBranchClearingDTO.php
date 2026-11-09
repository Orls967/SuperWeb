<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\DTO;

readonly class InterBranchClearingDTO
{
    public function __construct(
        public int $fromBranchId,
        public int $toBranchId,
        public string $referenceType,
        public string $referenceId,
        public int $amountIdr
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fromBranchId: (int) ($data['from_branch_id'] ?? 0),
            toBranchId: (int) ($data['to_branch_id'] ?? 0),
            referenceType: (string) ($data['reference_type'] ?? 'CROSS_BRANCH_LOT_TRANSFER'),
            referenceId: (string) ($data['reference_id'] ?? 'REF-'.time()),
            amountIdr: (int) ($data['amount_idr'] ?? 0),
        );
    }
}
